/**
 * SAMS Service Worker
 * Handles offline caching, background sync, and push notifications
 * Version: 1.0.0
 */

const CACHE_VERSION = 'sams-v1.0.0';
const CACHE_NAME = `sams-cache-${CACHE_VERSION}`;
const DATA_CACHE_NAME = `sams-data-${CACHE_VERSION}`;

// Assets to cache immediately
const STATIC_ASSETS = [
    '/attendance/',
    '/attendance/index.php',
    '/attendance/login.php',
    '/attendance/assets/css/cyberpunk-ui.css',
    '/attendance/assets/js/main.js',
    '/attendance/assets/images/icons/icon-192x192.png',
    '/attendance/assets/images/icons/icon-512x512.png',
    '/attendance/offline.html'
];

// Cache strategies
const CACHE_STRATEGIES = {
    pages: 'network-first',
    assets: 'cache-first',
    api: 'network-only',
    images: 'cache-first'
};

// Install event - cache static assets
self.addEventListener('install', (event) => {
    console.log('[SW] Installing service worker...');

    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => {
                console.log('[SW] Caching static assets');
                return cache.addAll(STATIC_ASSETS);
            })
            .then(() => self.skipWaiting())
    );
});

// Activate event - clean old caches
self.addEventListener('activate', (event) => {
    console.log('[SW] Activating service worker...');

    event.waitUntil(
        caches.keys()
            .then((cacheNames) => {
                return Promise.all(
                    cacheNames.map((cacheName) => {
                        if (cacheName !== CACHE_NAME && cacheName !== DATA_CACHE_NAME) {
                            console.log('[SW] Deleting old cache:', cacheName);
                            return caches.delete(cacheName);
                        }
                    })
                );
            })
            .then(() => self.clients.claim())
    );
});

// Fetch event - serve from cache with network fallback
self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    // Skip non-GET requests
    if (request.method !== 'GET') return;

    // API requests - network-first with cache fallback
    if (url.pathname.includes('/api/')) {
        event.respondWith(networkFirstStrategy(request, DATA_CACHE_NAME));
        return;
    }

    // PHP pages - network-first
    if (url.pathname.endsWith('.php')) {
        event.respondWith(networkFirstStrategy(request, CACHE_NAME));
        return;
    }

    // Static assets - cache-first
    if (url.pathname.match(/\.(css|js|png|jpg|jpeg|svg|gif|woff|woff2|ttf)$/)) {
        event.respondWith(cacheFirstStrategy(request, CACHE_NAME));
        return;
    }

    // Default - network-first
    event.respondWith(networkFirstStrategy(request, CACHE_NAME));
});

// Cache-first strategy
async function cacheFirstStrategy(request, cacheName) {
    const cache = await caches.open(cacheName);
    const cached = await cache.match(request);

    if (cached) {
        return cached;
    }

    try {
        const response = await fetch(request);
        if (response.ok) {
            cache.put(request, response.clone());
        }
        return response;
    } catch (error) {
        console.error('[SW] Fetch failed:', error);
        return new Response('Network error', { status: 408 });
    }
}

// Network-first strategy
async function networkFirstStrategy(request, cacheName) {
    const cache = await caches.open(cacheName);

    try {
        const response = await fetch(request);
        if (response.ok) {
            cache.put(request, response.clone());
        }
        return response;
    } catch (error) {
        console.error('[SW] Network failed, trying cache:', error);
        const cached = await cache.match(request);

        if (cached) {
            return cached;
        }

        // Return offline page for navigation requests
        if (request.mode === 'navigate') {
            return cache.match('/attendance/offline.html');
        }

        return new Response('Network error', {
            status: 503,
            statusText: 'Service Unavailable'
        });
    }
}

// Background sync for offline actions
self.addEventListener('sync', (event) => {
    console.log('[SW] Background sync triggered:', event.tag);

    if (event.tag === 'sync-attendance') {
        event.waitUntil(syncAttendance());
    } else if (event.tag === 'sync-messages') {
        event.waitUntil(syncMessages());
    } else if (event.tag === 'sync-submissions') {
        event.waitUntil(syncSubmissions());
    }
});

// Sync attendance data
async function syncAttendance() {
    try {
        const db = await openDB();
        const tx = db.transaction('pendingAttendance', 'readonly');
        const store = tx.objectStore('pendingAttendance');
        const records = await store.getAll();

        for (const record of records) {
            const response = await fetch('/attendance/api/attendance.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(record.data)
            });

            if (response.ok) {
                const deleteTx = db.transaction('pendingAttendance', 'readwrite');
                deleteTx.objectStore('pendingAttendance').delete(record.id);
            }
        }

        console.log('[SW] Attendance synced successfully');
    } catch (error) {
        console.error('[SW] Attendance sync failed:', error);
        throw error;
    }
}

// Sync messages
async function syncMessages() {
    try {
        const db = await openDB();
        const tx = db.transaction('pendingMessages', 'readonly');
        const store = tx.objectStore('pendingMessages');
        const messages = await store.getAll();

        for (const msg of messages) {
            const response = await fetch('/attendance/api/messages.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(msg.data)
            });

            if (response.ok) {
                const deleteTx = db.transaction('pendingMessages', 'readwrite');
                deleteTx.objectStore('pendingMessages').delete(msg.id);
            }
        }

        console.log('[SW] Messages synced successfully');
    } catch (error) {
        console.error('[SW] Message sync failed:', error);
        throw error;
    }
}

// Sync assignment submissions
async function syncSubmissions() {
    try {
        const db = await openDB();
        const tx = db.transaction('pendingSubmissions', 'readonly');
        const store = tx.objectStore('pendingSubmissions');
        const submissions = await store.getAll();

        for (const submission of submissions) {
            const formData = new FormData();
            Object.keys(submission.data).forEach(key => {
                formData.append(key, submission.data[key]);
            });

            const response = await fetch('/attendance/api/assignments.php', {
                method: 'POST',
                body: formData
            });

            if (response.ok) {
                const deleteTx = db.transaction('pendingSubmissions', 'readwrite');
                deleteTx.objectStore('pendingSubmissions').delete(submission.id);
            }
        }

        console.log('[SW] Submissions synced successfully');
    } catch (error) {
        console.error('[SW] Submission sync failed:', error);
        throw error;
    }
}

// Push notification handler
self.addEventListener('push', (event) => {
    console.log('[SW] Push notification received');

    const options = {
        icon: '/attendance/assets/images/icons/icon-192x192.png',
        badge: '/attendance/assets/images/icons/badge-72x72.png',
        vibrate: [200, 100, 200],
        requireInteraction: false,
        actions: []
    };

    let notification = {
        title: 'SAMS Notification',
        body: 'You have a new update'
    };

    if (event.data) {
        const data = event.data.json();
        notification = {
            title: data.title || notification.title,
            body: data.message || notification.body,
            ...data.options
        };

        // Add action buttons based on notification type
        if (data.type === 'attendance') {
            options.actions = [
                { action: 'view', title: 'View Details', icon: '/attendance/assets/images/icons/view.png' },
                { action: 'dismiss', title: 'Dismiss', icon: '/attendance/assets/images/icons/dismiss.png' }
            ];
            options.data = { url: '/attendance/student/attendance.php' };
        } else if (data.type === 'message') {
            options.actions = [
                { action: 'reply', title: 'Reply', icon: '/attendance/assets/images/icons/reply.png' },
                { action: 'view', title: 'View', icon: '/attendance/assets/images/icons/view.png' }
            ];
            options.data = { url: '/attendance/messages.php' };
        } else if (data.type === 'assignment') {
            options.actions = [
                { action: 'view', title: 'View Assignment', icon: '/attendance/assets/images/icons/assignment.png' }
            ];
            options.data = { url: '/attendance/student/assignments.php' };
        }

        if (data.url) {
            options.data = { url: data.url };
        }
    }

    event.waitUntil(
        self.registration.showNotification(notification.title, {
            body: notification.body,
            ...options
        })
    );
});

// Notification click handler
self.addEventListener('notificationclick', (event) => {
    console.log('[SW] Notification clicked:', event.action);

    event.notification.close();

    const urlToOpen = event.notification.data?.url || '/attendance/';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true })
            .then((clientList) => {
                // Check if already open
                for (const client of clientList) {
                    if (client.url === urlToOpen && 'focus' in client) {
                        return client.focus();
                    }
                }

                // Open new window
                if (clients.openWindow) {
                    return clients.openWindow(urlToOpen);
                }
            })
    );
});

// Periodic background sync (for updates)
self.addEventListener('periodicsync', (event) => {
    console.log('[SW] Periodic sync triggered:', event.tag);

    if (event.tag === 'update-content') {
        event.waitUntil(updateContent());
    }
});

async function updateContent() {
    try {
        // Fetch fresh data for user dashboards
        await fetch('/attendance/api/sync.php?action=check_updates');
        console.log('[SW] Content updated successfully');
    } catch (error) {
        console.error('[SW] Content update failed:', error);
    }
}

// IndexedDB helper
function openDB() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open('SAMS_Offline', 1);

        request.onerror = () => reject(request.error);
        request.onsuccess = () => resolve(request.result);

        request.onupgradeneeded = (event) => {
            const db = event.target.result;

            if (!db.objectStoreNames.contains('pendingAttendance')) {
                db.createObjectStore('pendingAttendance', { keyPath: 'id', autoIncrement: true });
            }
            if (!db.objectStoreNames.contains('pendingMessages')) {
                db.createObjectStore('pendingMessages', { keyPath: 'id', autoIncrement: true });
            }
            if (!db.objectStoreNames.contains('pendingSubmissions')) {
                db.createObjectStore('pendingSubmissions', { keyPath: 'id', autoIncrement: true });
            }
            if (!db.objectStoreNames.contains('cachedData')) {
                db.createObjectStore('cachedData', { keyPath: 'key' });
            }
        };
    });
}

// Message handler from clients
self.addEventListener('message', (event) => {
    console.log('[SW] Message received:', event.data);

    if (event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    } else if (event.data.type === 'CACHE_URLS') {
        event.waitUntil(
            caches.open(CACHE_NAME)
                .then(cache => cache.addAll(event.data.urls))
        );
    } else if (event.data.type === 'CLEAR_CACHE') {
        event.waitUntil(
            caches.keys()
                .then(names => Promise.all(names.map(name => caches.delete(name))))
        );
    }
});
