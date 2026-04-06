/**
 * UI Watchdog — Frontend Health Monitor
 *
 * Detects: empty containers, failed API calls, stale panels, broken images.
 * Actions: auto-reload components, show toast alerts, report issues to backend.
 */
(function () {
  "use strict";

  const WATCHDOG_INTERVAL = 30000; // Check every 30 seconds
  const API_TIMEOUT = 10000; // API calls timeout after 10s
  const MAX_RETRIES = 3;

  let failedApiCount = 0;
  let watchdogActive = true;

  /**
   * Scan for empty content containers that should have data.
   */
  function scanEmptyContainers() {
    const selectors = [
      ".dashboard-card .card-body",
      ".data-table tbody",
      ".content-area",
      "#main-content",
      ".stats-container",
    ];

    const issues = [];
    selectors.forEach((sel) => {
      document.querySelectorAll(sel).forEach((el) => {
        if (el.children.length === 0 && el.textContent.trim() === "") {
          issues.push({
            type: "empty_container",
            selector: sel,
            element: el.id || el.className,
          });
        }
      });
    });

    return issues;
  }

  /**
   * Scan for broken images.
   */
  function scanBrokenImages() {
    const issues = [];
    document.querySelectorAll("img").forEach((img) => {
      if (img.naturalWidth === 0 && img.complete && img.src) {
        issues.push({
          type: "broken_image",
          src: img.src,
          alt: img.alt || "(no alt)",
        });
      }
    });
    return issues;
  }

  /**
   * Monitor API health by wrapping fetch.
   */
  function monitorAPIs() {
    const originalFetch = window.fetch;
    window.fetch = function (...args) {
      const url = typeof args[0] === "string" ? args[0] : args[0]?.url || "";

      return originalFetch
        .apply(this, args)
        .then((response) => {
          if (!response.ok && response.status >= 500) {
            failedApiCount++;
            reportIssue({
              type: "api_error",
              url: url,
              status: response.status,
            });
          }
          return response;
        })
        .catch((err) => {
          failedApiCount++;
          reportIssue({
            type: "api_failure",
            url: url,
            error: err.message,
          });
          throw err;
        });
    };
  }

  /**
   * Check if any panels appear stale (haven't updated in expected timeframe).
   */
  function scanStalePanels() {
    const issues = [];
    document.querySelectorAll("[data-refresh-interval]").forEach((el) => {
      const interval = parseInt(el.dataset.refreshInterval, 10) * 1000;
      const lastUpdate = parseInt(el.dataset.lastUpdate || "0", 10);
      const now = Date.now();

      if (lastUpdate > 0 && now - lastUpdate > interval * 2) {
        issues.push({
          type: "stale_panel",
          element: el.id || el.className,
          staleSince: new Date(lastUpdate).toISOString(),
        });

        // Try to trigger refresh
        if (typeof el.dataset.refreshUrl === "string") {
          reloadPanel(el);
        }
      }
    });
    return issues;
  }

  /**
   * Attempt to reload a stale panel.
   */
  function reloadPanel(el) {
    const url = el.dataset.refreshUrl;
    if (!url) return;

    fetch(url, { signal: AbortSignal.timeout(API_TIMEOUT) })
      .then((r) => r.text())
      .then((html) => {
        el.innerHTML = html;
        el.dataset.lastUpdate = Date.now().toString();
        showToast("Panel refreshed", "info");
      })
      .catch(() => {
        // Silent fail — already tracked
      });
  }

  /**
   * Report an issue to the backend for the autofix loop.
   */
  function reportIssue(issue) {
    const basePath =
      document.querySelector('meta[name="base-path"]')?.content || "";
    const endpoint = basePath + "/api/system-health.php";

    // Fire and forget — don't block UI
    navigator.sendBeacon(
      endpoint,
      JSON.stringify({
        source: "ui-watchdog",
        issue: issue,
        page: window.location.pathname,
        timestamp: new Date().toISOString(),
      }),
    );
  }

  /**
   * Show a non-intrusive toast notification.
   */
  function showToast(message, type) {
    // Only show if toast container exists (optional)
    let container = document.getElementById("watchdog-toasts");
    if (!container) {
      container = document.createElement("div");
      container.id = "watchdog-toasts";
      container.style.cssText =
        "position:fixed;bottom:20px;right:20px;z-index:99999;";
      document.body.appendChild(container);
    }

    const toast = document.createElement("div");
    const bgColor =
      type === "error" ? "#dc3545" : type === "warning" ? "#ffc107" : "#17a2b8";
    const textColor = type === "warning" ? "#333" : "#fff";
    toast.style.cssText = `background:${bgColor};color:${textColor};padding:10px 18px;margin-top:8px;border-radius:6px;font-size:13px;box-shadow:0 2px 8px rgba(0,0,0,.15);opacity:0;transition:opacity .3s;`;
    toast.textContent = message;
    container.appendChild(toast);

    requestAnimationFrame(() => {
      toast.style.opacity = "1";
    });
    setTimeout(() => {
      toast.style.opacity = "0";
      setTimeout(() => toast.remove(), 300);
    }, 4000);
  }

  /**
   * Main watchdog cycle.
   */
  function runWatchdog() {
    if (!watchdogActive) return;

    const issues = [
      ...scanEmptyContainers(),
      ...scanBrokenImages(),
      ...scanStalePanels(),
    ];

    if (issues.length > 0) {
      console.warn(
        "[UI Watchdog]",
        issues.length,
        "issue(s) detected:",
        issues,
      );
    }

    // If too many API failures, show warning
    if (failedApiCount >= 5) {
      showToast(
        "Multiple API failures detected — system may need attention",
        "warning",
      );
      failedApiCount = 0; // Reset counter
    }
  }

  /**
   * Initialize watchdog.
   */
  function init() {
    // Wrap fetch for API monitoring
    monitorAPIs();

    // Run first check after page load settles
    setTimeout(runWatchdog, 3000);

    // Periodic checks
    setInterval(runWatchdog, WATCHDOG_INTERVAL);

    // Monitor for JS errors
    window.addEventListener("error", function (e) {
      reportIssue({
        type: "js_error",
        message: e.message,
        source: e.filename,
        line: e.lineno,
      });
    });

    // Monitor unhandled promise rejections
    window.addEventListener("unhandledrejection", function (e) {
      reportIssue({
        type: "promise_rejection",
        message: String(e.reason),
      });
    });

    console.log(
      "[UI Watchdog] Active — monitoring every " +
        WATCHDOG_INTERVAL / 1000 +
        "s",
    );
  }

  // Start when DOM is ready
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }

  // Expose control API
  window.UIWatchdog = {
    pause: function () {
      watchdogActive = false;
      console.log("[UI Watchdog] Paused");
    },
    resume: function () {
      watchdogActive = true;
      console.log("[UI Watchdog] Resumed");
    },
    scan: runWatchdog,
    getFailedApiCount: function () {
      return failedApiCount;
    },
  };
})();
