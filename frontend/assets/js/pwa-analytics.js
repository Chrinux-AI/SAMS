/**
 * PWA Installation Tracker
 * Tracks PWA installations and analytics
 */

class PWAAnalytics {
    constructor() {
        this.userId = null;
        this.init();
    }

    async init() {
        // Get user ID from session
        this.userId = await this.getUserId();

        // Track installation
        if (this.isInstalled()) {
            this.trackEvent('install', {
                device_type: this.getDeviceType(),
                browser: this.getBrowser(),
                os: this.getOS(),
                screen_resolution: `${screen.width}x${screen.height}`
            });
        }

        // Track page views
        this.trackPageView();

        // Track offline access
        if (!navigator.onLine) {
            this.trackEvent('offline_access', {
                page_url: window.location.pathname
            });
        }
    }

    isInstalled() {
        return window.matchMedia('(display-mode: standalone)').matches ||
               window.navigator.standalone === true;
    }

    getDeviceType() {
        const ua = navigator.userAgent;
        if (/iPad|iPhone|iPod/.test(ua)) return 'ios';
        if (/android/i.test(ua)) return 'android';
        if (/Windows|Mac|Linux/.test(ua)) return 'desktop';
        return 'other';
    }

    getBrowser() {
        const ua = navigator.userAgent;
        if (ua.includes('Chrome')) return 'Chrome';
        if (ua.includes('Firefox')) return 'Firefox';
        if (ua.includes('Safari')) return 'Safari';
        if (ua.includes('Edge')) return 'Edge';
        return 'Unknown';
    }

    getOS() {
        const ua = navigator.userAgent;
        if (ua.includes('Windows')) return 'Windows';
        if (ua.includes('Mac')) return 'macOS';
        if (ua.includes('Linux')) return 'Linux';
        if (ua.includes('Android')) return 'Android';
        if (ua.includes('iOS')) return 'iOS';
        return 'Unknown';
    }

    async getUserId() {
        try {
            const response = await fetch('/attendance/api/session.php');
            const data = await response.json();
            return data.user_id || null;
        } catch (error) {
            return null;
        }
    }

    async trackEvent(eventType, eventData = {}) {
        try {
            await fetch('/attendance/api/pwa-analytics.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'track_event',
                    event_type: eventType,
                    event_data: eventData,
                    page_url: window.location.pathname,
                    is_offline: !navigator.onLine
                })
            });
        } catch (error) {
            console.error('Analytics tracking error:', error);
        }
    }

    trackPageView() {
        this.trackEvent('page_view', {
            referrer: document.referrer,
            title: document.title
        });
    }

    trackShare() {
        this.trackEvent('share', {
            page_url: window.location.href
        });
    }

    trackNotificationClick(notificationId) {
        this.trackEvent('notification_click', {
            notification_id: notificationId
        });
    }

    trackSync() {
        this.trackEvent('sync', {
            timestamp: Date.now()
        });
    }
}

// Initialize analytics
const pwaAnalytics = new PWAAnalytics();

// Export
window.PWAAnalytics = PWAAnalytics;
window.pwaAnalytics = pwaAnalytics;

/**
 * Enhanced Web Share functionality
 */
async function shareContent(title, text, url) {
    if (navigator.share) {
        try {
            await navigator.share({
                title: title || document.title,
                text: text || '',
                url: url || window.location.href
            });

            pwaAnalytics.trackShare();
            console.log('[PWA] Content shared successfully');
        } catch (error) {
            if (error.name !== 'AbortError') {
                console.error('[PWA] Share failed:', error);
            }
        }
    } else {
        // Fallback - copy to clipboard
        const shareUrl = url || window.location.href;
        try {
            await navigator.clipboard.writeText(shareUrl);
            if (window.pwaManager && typeof window.pwaManager.showToast === 'function') {
                window.pwaManager.showToast('Link copied to clipboard!', 'success');
            }
        } catch (error) {
            console.error('[PWA] Clipboard write failed:', error);
        }
    }
}

// Add share buttons to pages
function addShareButton() {
    const shareBtn = document.createElement('button');
    shareBtn.className = 'pwa-share-btn';
    shareBtn.innerHTML = '<i class="fas fa-share-alt"></i>';
    shareBtn.onclick = () => shareContent();
    shareBtn.title = 'Share this page';

    document.body.appendChild(shareBtn);
}

// Initialize share button if supported
if (navigator.share || navigator.clipboard) {
    addShareButton();
}

/**
 * Offline queue management UI
 */
function showOfflineQueue() {
    if (!window.pwaManager || !window.pwaManager.db) return;

    const modal = document.createElement('div');
    modal.className = 'offline-queue-modal';
    modal.innerHTML = `
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-clock"></i> Pending Sync Items</h3>
                <button onclick="this.parentElement.parentElement.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body" id="queue-items">
                Loading...
            </div>
            <div class="modal-footer">
                <button onclick="window.pwaManager.syncOfflineData()" class="btn-primary">
                    <i class="fas fa-sync"></i> Sync Now
                </button>
                <button onclick="this.parentElement.parentElement.parentElement.remove()" class="btn-secondary">
                    Close
                </button>
            </div>
        </div>
    `;

    document.body.appendChild(modal);

    // Load queue items
    loadQueueItems();
}

async function loadQueueItems() {
    const container = document.getElementById('queue-items');
    if (!container) return;

    try {
        const stores = ['pendingAttendance', 'pendingMessages', 'pendingSubmissions'];
        let html = '';

        for (const storeName of stores) {
            const tx = window.pwaManager.db.transaction(storeName, 'readonly');
            const store = tx.objectStore(storeName);
            const items = await window.pwaManager.getAll(store);

            if (items.length > 0) {
                html += `
                    <div class="queue-section">
                        <h4>${storeName.replace('pending', '')}</h4>
                        <ul>
                            ${items.map(item => `
                                <li>
                                    <i class="fas fa-clock"></i>
                                    ${new Date(item.timestamp).toLocaleString()}
                                    <span class="pending-actions-badge">${items.length}</span>
                                </li>
                            `).join('')}
                        </ul>
                    </div>
                `;
            }
        }

        if (html === '') {
            html = '<p style="text-align: center; color: rgba(255,255,255,0.6);">No pending items</p>';
        }

        container.innerHTML = html;
    } catch (error) {
        console.error('Load queue items error:', error);
        container.innerHTML = '<p style="color: #f44336;">Failed to load queue items</p>';
    }
}

/**
 * Add connection status indicator to all pages
 */
function addConnectionStatus() {
    const statusEl = document.createElement('div');
    statusEl.id = 'connection-status';
    statusEl.className = navigator.onLine ? 'connection-status online' : 'connection-status offline';
    statusEl.innerHTML = navigator.onLine ?
        '<i class="fas fa-wifi"></i> Online' :
        '<i class="fas fa-wifi-slash"></i> Offline';

    document.body.appendChild(statusEl);
}

// Initialize on load
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        addConnectionStatus();
    });
} else {
    addConnectionStatus();
}

/**
 * Voice commands for PWA (experimental)
 */
if ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window) {
    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    const recognition = new SpeechRecognition();

    recognition.continuous = false;
    recognition.lang = 'en-US';

    recognition.onresult = (event) => {
        const command = event.results[0][0].transcript.toLowerCase();
        console.log('[PWA] Voice command:', command);

        if (command.includes('check in') || command.includes('check-in')) {
            window.location.href = '/attendance/student/checkin.php';
        } else if (command.includes('schedule')) {
            window.location.href = '/attendance/student/schedule.php';
        } else if (command.includes('messages')) {
            window.location.href = '/attendance/messages.php';
        }
    };

    // Add voice button
    function addVoiceButton() {
        const voiceBtn = document.createElement('button');
        voiceBtn.className = 'pwa-voice-btn';
        voiceBtn.innerHTML = '<i class="fas fa-microphone"></i>';
        voiceBtn.onclick = () => recognition.start();
        voiceBtn.title = 'Voice command';

        document.body.appendChild(voiceBtn);
    }

    addVoiceButton();
}
