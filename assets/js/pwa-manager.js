/**
 * PWA Manager
 * Handles service worker registration, installation prompts, and offline sync
 */

class PWAManager {
  constructor() {
    this.deferredPrompt = null;
    this.isInstalled = false;
    this.isOnline = navigator.onLine;
    this.syncQueue = [];
    this.db = null;

    this.init();
  }

  async init() {
    // Check if PWA is already installed
    this.checkInstallation();

    // Register service worker
    if ("serviceWorker" in navigator) {
      this.registerServiceWorker();
    }

    // Setup install prompt
    this.setupInstallPrompt();

    // Setup online/offline detection
    this.setupNetworkDetection();

    // Initialize IndexedDB
    await this.initDB();

    // Setup push notifications
    this.setupPushNotifications();

    // Setup background sync
    this.setupBackgroundSync();

    // Update UI based on connection status
    this.updateConnectionStatus();
  }

  async registerServiceWorker() {
    try {
      const registration = await navigator.serviceWorker.register(
        "/attendance/sw.js",
        {
          scope: "/attendance/",
        },
      );

      console.log("[PWA] Service Worker registered:", registration);

      // Check for updates
      registration.addEventListener("updatefound", () => {
        const newWorker = registration.installing;

        newWorker.addEventListener("statechange", () => {
          if (
            newWorker.state === "installed" &&
            navigator.serviceWorker.controller
          ) {
            this.showUpdateNotification();
          }
        });
      });

      // Handle controller change
      navigator.serviceWorker.addEventListener("controllerchange", () => {
        window.location.reload();
      });

      return registration;
    } catch (error) {
      console.error("[PWA] Service Worker registration failed:", error);
    }
  }

  setupInstallPrompt() {
    window.addEventListener("beforeinstallprompt", (e) => {
      e.preventDefault();
      this.deferredPrompt = e;
      this.showInstallButton();
    });

    window.addEventListener("appinstalled", () => {
      console.log("[PWA] App installed successfully");
      this.isInstalled = true;
      this.hideInstallButton();
      this.showToast("SAMS installed successfully!", "success");
    });
  }

  async showInstallPrompt() {
    if (!this.deferredPrompt) {
      console.log("[PWA] Install prompt not available");
      return;
    }

    this.deferredPrompt.prompt();
    const { outcome } = await this.deferredPrompt.userChoice;

    console.log(`[PWA] User response: ${outcome}`);
    this.deferredPrompt = null;
  }

  showInstallButton() {
    const existingBtn = document.getElementById("pwa-install-btn");
    if (existingBtn) return;

    const installBtn = document.createElement("button");
    installBtn.id = "pwa-install-btn";
    installBtn.className = "pwa-install-btn";
    installBtn.innerHTML = `
            <i class="fas fa-download"></i>
            <span>Install App</span>
        `;
    installBtn.onclick = () => this.showInstallPrompt();

    document.body.appendChild(installBtn);
  }

  hideInstallButton() {
    const installBtn = document.getElementById("pwa-install-btn");
    if (installBtn) {
      installBtn.remove();
    }
  }

  checkInstallation() {
    // Check if running as standalone PWA
    if (
      window.matchMedia("(display-mode: standalone)").matches ||
      window.navigator.standalone === true
    ) {
      this.isInstalled = true;
      console.log("[PWA] Running as installed app");
    }
  }

  setupNetworkDetection() {
    window.addEventListener("online", () => {
      console.log("[PWA] Back online");
      this.isOnline = true;
      this.updateConnectionStatus();
      this.syncOfflineData();
      this.showToast("Back online - syncing data...", "success");
    });

    window.addEventListener("offline", () => {
      console.log("[PWA] Gone offline");
      this.isOnline = false;
      this.updateConnectionStatus();
      this.showToast(
        "You are offline - data will sync when reconnected",
        "warning",
      );
    });
  }

  updateConnectionStatus() {
    const statusEl = document.getElementById("connection-status");
    if (!statusEl) return;

    if (this.isOnline) {
      statusEl.innerHTML = '<i class="fas fa-wifi"></i> Online';
      statusEl.className = "connection-status online";
    } else {
      statusEl.innerHTML = '<i class="fas fa-wifi-slash"></i> Offline';
      statusEl.className = "connection-status offline";
    }
  }

  async initDB() {
    return new Promise((resolve, reject) => {
      const request = indexedDB.open("SAMS_Offline", 1);

      request.onerror = () => reject(request.error);
      request.onsuccess = () => {
        this.db = request.result;
        resolve(this.db);
      };

      request.onupgradeneeded = (event) => {
        const db = event.target.result;

        // Pending attendance records
        if (!db.objectStoreNames.contains("pendingAttendance")) {
          const store = db.createObjectStore("pendingAttendance", {
            keyPath: "id",
            autoIncrement: true,
          });
          store.createIndex("timestamp", "timestamp", { unique: false });
        }

        // Pending messages
        if (!db.objectStoreNames.contains("pendingMessages")) {
          const store = db.createObjectStore("pendingMessages", {
            keyPath: "id",
            autoIncrement: true,
          });
          store.createIndex("timestamp", "timestamp", { unique: false });
        }

        // Pending submissions
        if (!db.objectStoreNames.contains("pendingSubmissions")) {
          const store = db.createObjectStore("pendingSubmissions", {
            keyPath: "id",
            autoIncrement: true,
          });
          store.createIndex("timestamp", "timestamp", { unique: false });
        }

        // Cached data
        if (!db.objectStoreNames.contains("cachedData")) {
          const store = db.createObjectStore("cachedData", { keyPath: "key" });
          store.createIndex("timestamp", "timestamp", { unique: false });
        }
      };
    });
  }

  async queueOfflineAction(storeName, data) {
    if (!this.db) {
      await this.initDB();
    }

    return new Promise((resolve, reject) => {
      const tx = this.db.transaction(storeName, "readwrite");
      const store = tx.objectStore(storeName);

      const request = store.add({
        data: data,
        timestamp: Date.now(),
      });

      request.onsuccess = () => {
        console.log(`[PWA] Queued offline action in ${storeName}`);
        resolve(request.result);
      };
      request.onerror = () => reject(request.error);
    });
  }

  async syncOfflineData() {
    if (!this.isOnline || !this.db) return;

    console.log("[PWA] Starting offline data sync...");

    await this.syncStore(
      "pendingAttendance",
      "/attendance/api/sync.php?action=sync_attendance",
      "records",
    );
    await this.syncStore(
      "pendingMessages",
      "/attendance/api/sync.php?action=sync_messages",
      "messages",
    );
    await this.syncStore(
      "pendingSubmissions",
      "/attendance/api/sync.php?action=sync_submissions",
      "submissions",
    );

    this.showToast("All offline data synced successfully", "success");
  }

  async syncStore(storeName, endpoint, collectionKey) {
    const tx = this.db.transaction(storeName, "readonly");
    const store = tx.objectStore(storeName);
    const records = await this.getAll(store);

    console.log(`[PWA] Syncing ${records.length} records from ${storeName}`);

    for (const record of records) {
      try {
        const response = await fetch(endpoint, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ [collectionKey]: [record.data] }),
        });

        if (!response.ok) {
          continue;
        }

        // Only delete local queued records when API explicitly confirms success.
        const payload = await response.json();
        if (payload && payload.success === true) {
          await this.deleteRecord(storeName, record.id);
          console.log(`[PWA] Synced record ${record.id} from ${storeName}`);
        }
      } catch (error) {
        console.error(`[PWA] Failed to sync record ${record.id}:`, error);
      }
    }
  }

  getAll(store) {
    return new Promise((resolve, reject) => {
      const request = store.getAll();
      request.onsuccess = () => resolve(request.result);
      request.onerror = () => reject(request.error);
    });
  }

  async deleteRecord(storeName, id) {
    const tx = this.db.transaction(storeName, "readwrite");
    const store = tx.objectStore(storeName);
    return new Promise((resolve, reject) => {
      const request = store.delete(id);
      request.onsuccess = () => resolve();
      request.onerror = () => reject(request.error);
    });
  }

  async setupPushNotifications() {
    if (!("Notification" in window) || !("PushManager" in window)) {
      console.log("[PWA] Push notifications not supported");
      return;
    }

    // Request permission if not granted
    if (Notification.permission === "default") {
      this.showNotificationPrompt();
    } else if (Notification.permission === "granted") {
      await this.subscribeToPush();
    }
  }

  showNotificationPrompt() {
    const existingPrompt = document.getElementById("notification-prompt");
    if (existingPrompt) return;

    const prompt = document.createElement("div");
    prompt.id = "notification-prompt";
    prompt.className = "notification-prompt";
    prompt.innerHTML = `
            <div class="notification-prompt-content">
                <i class="fas fa-bell"></i>
                <div>
                    <h4>Enable Notifications</h4>
                    <p>Stay updated with attendance alerts and messages</p>
                </div>
                <button onclick="pwaManager.requestNotificationPermission()">Enable</button>
                <button onclick="this.parentElement.parentElement.remove()">Later</button>
            </div>
        `;

    document.body.appendChild(prompt);
  }

  async requestNotificationPermission() {
    const permission = await Notification.requestPermission();

    if (permission === "granted") {
      console.log("[PWA] Notification permission granted");
      await this.subscribeToPush();
      document.getElementById("notification-prompt")?.remove();
      this.showToast("Notifications enabled!", "success");
    } else {
      this.showToast("Notification permission denied", "error");
    }
  }

  async subscribeToPush() {
    try {
      const registration = await navigator.serviceWorker.ready;

      // Check if already subscribed
      let subscription = await registration.pushManager.getSubscription();

      if (!subscription) {
        // Subscribe to push
        subscription = await registration.pushManager.subscribe({
          userVisibleOnly: true,
          applicationServerKey: this.urlBase64ToUint8Array(VAPID_PUBLIC_KEY),
        });

        // Send subscription to server
        await fetch("/attendance/api/push.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            action: "subscribe",
            subscription: subscription,
          }),
        });

        console.log("[PWA] Push subscription successful");
      }
    } catch (error) {
      console.error("[PWA] Push subscription failed:", error);
    }
  }

  urlBase64ToUint8Array(base64String) {
    const padding = "=".repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding)
      .replace(/\-/g, "+")
      .replace(/_/g, "/");

    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);

    for (let i = 0; i < rawData.length; ++i) {
      outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
  }

  setupBackgroundSync() {
    if (!("serviceWorker" in navigator)) {
      console.log("[PWA] Service Worker not supported");
      return;
    }

    navigator.serviceWorker.ready
      .then((registration) => {
        if ("sync" in registration) {
          console.log("[PWA] Background sync supported");
        } else {
          console.log("[PWA] Background sync not supported - using fallback");
        }
      })
      .catch((error) => {
        console.error("[PWA] Service Worker not ready for sync check:", error);
      });
  }

  async registerSync(tag) {
    try {
      const registration = await navigator.serviceWorker.ready;
      await registration.sync.register(tag);
      console.log(`[PWA] Registered background sync: ${tag}`);
    } catch (error) {
      console.error("[PWA] Background sync registration failed:", error);
      // Fallback: sync immediately
      this.syncOfflineData();
    }
  }

  showUpdateNotification() {
    const notification = document.createElement("div");
    notification.className = "update-notification";
    notification.innerHTML = `
            <div class="update-notification-content">
                <i class="fas fa-sync-alt"></i>
                <span>A new version is available!</span>
                <button onclick="pwaManager.updateApp()">Update Now</button>
            </div>
        `;

    document.body.appendChild(notification);
  }

  updateApp() {
    if (navigator.serviceWorker.controller) {
      navigator.serviceWorker.controller.postMessage({ type: "SKIP_WAITING" });
    }
  }

  showToast(message, type = "info") {
    const toast = document.createElement("div");
    toast.className = `pwa-toast pwa-toast-${type}`;
    toast.innerHTML = `
            <i class="fas fa-${this.getToastIcon(type)}"></i>
            <span>${message}</span>
        `;

    document.body.appendChild(toast);

    setTimeout(() => {
      toast.classList.add("show");
    }, 100);

    setTimeout(() => {
      toast.classList.remove("show");
      setTimeout(() => toast.remove(), 300);
    }, 3000);
  }

  getToastIcon(type) {
    const icons = {
      success: "check-circle",
      error: "exclamation-circle",
      warning: "exclamation-triangle",
      info: "info-circle",
    };
    return icons[type] || "info-circle";
  }

  // Cache management
  async clearCache() {
    const cacheNames = await caches.keys();
    await Promise.all(cacheNames.map((name) => caches.delete(name)));
    console.log("[PWA] Cache cleared");
  }

  async getCacheSize() {
    if ("storage" in navigator && "estimate" in navigator.storage) {
      const estimate = await navigator.storage.estimate();
      return {
        usage: estimate.usage,
        quota: estimate.quota,
        percentage: ((estimate.usage / estimate.quota) * 100).toFixed(2),
      };
    }
    return null;
  }
}

// VAPID public key (replace with your actual key)
const VAPID_PUBLIC_KEY =
  "BEl62iUYgUivxIkv69yViEuiBIa-Ib37L8hxEvswJPg98BPWA2BU7qfhS_O3qUPPSJ7vBYpJmS7P2Fo9G3XMJoE";

// Initialize PWA Manager
let pwaManager;

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", () => {
    pwaManager = new PWAManager();
  });
} else {
  pwaManager = new PWAManager();
}

// Export for use in other scripts
window.PWAManager = PWAManager;
window.pwaManager = pwaManager;
