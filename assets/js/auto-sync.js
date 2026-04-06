/**
 * SAMS Auto-Sync Client — Phase-5 Self-Healing UI
 *
 * Listens for server-side events and auto-refreshes stale components.
 * Also runs a periodic consistency check against the API.
 *
 * Include on any page: <script src="/attendance/assets/js/auto-sync.js"></script>
 */
(function () {
  "use strict";

  const BASE = "/attendance";
  let sseConnection = null;
  let lastEventId = 0;
  let reconnectTimer = null;
  const RECONNECT_DELAY = 5000;
  const CONSISTENCY_INTERVAL = 30000; // 30s

  // ── SSE Connection ────────────────────────────────────────────

  function connectSSE() {
    if (sseConnection && sseConnection.readyState !== EventSource.CLOSED)
      return;

    try {
      sseConnection = new EventSource(
        BASE + "/api/sse.php?last_id=" + lastEventId,
      );

      sseConnection.onmessage = function (e) {
        try {
          const payload = JSON.parse(e.data);
          lastEventId = e.lastEventId || lastEventId;
          handleEvent(payload);
        } catch (err) {
          // non-JSON heartbeat — ignore
        }
      };

      sseConnection.onerror = function () {
        sseConnection.close();
        sseConnection = null;
        clearTimeout(reconnectTimer);
        reconnectTimer = setTimeout(connectSSE, RECONNECT_DELAY);
      };
    } catch (err) {
      // SSE not supported or blocked — fall back to polling
      startPolling();
    }
  }

  // ── Event Handler ─────────────────────────────────────────────

  function handleEvent(payload) {
    const event = payload.event || "";
    const table = event.split(".")[0] || "";
    const action = event.split(".")[1] || "";

    // Dispatch custom DOM event for page-specific handlers
    document.dispatchEvent(new CustomEvent("sams:sync", { detail: payload }));

    // Auto-refresh matching data regions
    const regions = document.querySelectorAll(
      '[data-sync-table="' + table + '"]',
    );
    regions.forEach(function (el) {
      refreshRegion(el, table, payload.record_id);
    });

    // Show toast notification for admin actions
    if (action === "created" || action === "updated" || action === "deleted") {
      showSyncToast(table, action, payload);
    }
  }

  // ── Region Refresh ────────────────────────────────────────────

  function refreshRegion(el, table, recordId) {
    const url = el.getAttribute("data-sync-url");
    if (url) {
      // AJAX partial reload
      fetch(url, { credentials: "same-origin" })
        .then(function (r) {
          return r.text();
        })
        .then(function (html) {
          el.innerHTML = html;
          el.classList.add("sync-flash");
          setTimeout(function () {
            el.classList.remove("sync-flash");
          }, 600);
        })
        .catch(function () {
          /* silent */
        });
    } else {
      // No partial URL — mark as stale for next page interaction
      el.setAttribute("data-stale", "true");
      el.classList.add("sync-stale");
    }
  }

  // ── Consistency Watchdog ──────────────────────────────────────

  function checkConsistency() {
    var staleEls = document.querySelectorAll('[data-stale="true"]');
    if (staleEls.length > 0) {
      // Targeted reload of stale sections only
      staleEls.forEach(function (el) {
        var url = el.getAttribute("data-sync-url");
        if (url) {
          refreshRegion(el, "", 0);
          el.removeAttribute("data-stale");
          el.classList.remove("sync-stale");
        }
      });
    }

    // Verify visible data-bound elements
    var boundEls = document.querySelectorAll("[data-verify-table]");
    boundEls.forEach(function (el) {
      var table = el.getAttribute("data-verify-table");
      var id = el.getAttribute("data-verify-id");
      var field = el.getAttribute("data-verify-field");
      if (!table || !id || !field) return;

      fetch(
        BASE +
          "/api/verify-field.php?table=" +
          encodeURIComponent(table) +
          "&id=" +
          encodeURIComponent(id) +
          "&field=" +
          encodeURIComponent(field),
        {
          credentials: "same-origin",
        },
      )
        .then(function (r) {
          return r.json();
        })
        .then(function (data) {
          if (
            data.value !== undefined &&
            el.textContent.trim() !== String(data.value).trim()
          ) {
            el.textContent = data.value;
            el.classList.add("sync-flash");
            setTimeout(function () {
              el.classList.remove("sync-flash");
            }, 600);
          }
        })
        .catch(function () {
          /* silent */
        });
    });
  }

  // ── Polling Fallback ──────────────────────────────────────────

  var pollTimer = null;
  function startPolling() {
    if (pollTimer) return;
    pollTimer = setInterval(checkConsistency, CONSISTENCY_INTERVAL);
  }

  // ── Toast ─────────────────────────────────────────────────────

  function showSyncToast(table, action, payload) {
    var container = document.getElementById("sync-toast-container");
    if (!container) {
      container = document.createElement("div");
      container.id = "sync-toast-container";
      container.style.cssText =
        "position:fixed;top:80px;right:20px;z-index:9999;display:flex;flex-direction:column;gap:8px;pointer-events:none;";
      document.body.appendChild(container);
    }

    var label = table.replace(/_/g, " ");
    var icons = { created: "✅", updated: "🔄", deleted: "🗑️" };
    var toast = document.createElement("div");
    toast.style.cssText =
      "background:var(--card-bg,#1a1d27);border:1px solid var(--primary,#4f46e5);color:var(--text-color,#f1f5f9);padding:12px 18px;border-radius:10px;font-size:0.85rem;box-shadow:0 4px 20px rgba(0,0,0,0.3);opacity:0;transform:translateX(30px);transition:all 0.3s;pointer-events:auto;";
    toast.textContent = (icons[action] || "📡") + " " + label + " " + action;

    container.appendChild(toast);
    requestAnimationFrame(function () {
      toast.style.opacity = "1";
      toast.style.transform = "translateX(0)";
    });
    setTimeout(function () {
      toast.style.opacity = "0";
      toast.style.transform = "translateX(30px)";
      setTimeout(function () {
        toast.remove();
      }, 300);
    }, 4000);
  }

  // ── Init ──────────────────────────────────────────────────────

  function init() {
    // Inject sync styles
    var style = document.createElement("style");
    style.textContent = [
      "@keyframes syncFlash{0%{box-shadow:0 0 0 0 rgba(79,70,229,.4)}50%{box-shadow:0 0 12px 4px rgba(79,70,229,.3)}100%{box-shadow:0 0 0 0 transparent}}",
      ".sync-flash{animation:syncFlash .6s ease-out}",
      ".sync-stale{border-left:3px solid #f59e0b !important}",
    ].join("\n");
    document.head.appendChild(style);

    // Connect SSE
    connectSSE();

    // Start consistency watchdog
    setInterval(checkConsistency, CONSISTENCY_INTERVAL);

    // Reconnect on visibility change
    document.addEventListener("visibilitychange", function () {
      if (!document.hidden) {
        connectSSE();
        checkConsistency();
      }
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
