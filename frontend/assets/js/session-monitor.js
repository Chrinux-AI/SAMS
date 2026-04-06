/**
 * SAMS Session Monitor
 * Client-side idle detection, session heartbeat, and auto-logout.
 * Included on all authenticated pages.
 */
(function () {
  "use strict";

  var HEARTBEAT_INTERVAL = 60000; // Check every 60s
  var WARNING_THRESHOLD = 120; // Show warning when 2 min left
  var idleTimer = null;
  var heartbeatTimer = null;
  var warningShown = false;
  var warningOverlay = null;

  function resetIdleTimer() {
    // The actual timeout enforcement is server-side;
    // client just sends heartbeats while user is active
    if (warningShown) {
      dismissWarning();
    }
  }

  function sendHeartbeat() {
    fetch("/attendance/api/session-heartbeat.php", {
      method: "GET",
      credentials: "same-origin",
      headers: { "X-Requested-With": "XMLHttpRequest" },
    })
      .then(function (r) {
        return r.json();
      })
      .then(function (data) {
        if (!data.authenticated) {
          redirectToLogin();
          return;
        }
        if (data.warning && !warningShown) {
          showWarning(data.remaining);
        }
      })
      .catch(function () {
        // Network error — don't disrupt user
      });
  }

  function showWarning(secondsLeft) {
    if (warningOverlay) return;
    warningShown = true;

    warningOverlay = document.createElement("div");
    warningOverlay.id = "session-warning-overlay";
    warningOverlay.innerHTML =
      '<div style="position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:99999;display:flex;align-items:center;justify-content:center">' +
      '<div style="background:#fff;border-radius:12px;padding:32px;max-width:400px;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,0.3)">' +
      '<i class="fas fa-clock" style="font-size:2.5rem;color:#f59e0b;margin-bottom:16px;display:block"></i>' +
      '<h3 style="margin:0 0 12px;color:#1e293b;font-size:1.25rem">Session Expiring Soon</h3>' +
      '<p style="color:#64748b;margin:0 0 20px">Your session will expire in <strong id="session-countdown">' +
      Math.ceil(secondsLeft) +
      "</strong> seconds due to inactivity.</p>" +
      "<button onclick=\"document.getElementById('session-warning-overlay').remove();fetch('/attendance/api/session-heartbeat.php',{credentials:'same-origin'})\" " +
      'style="padding:12px 24px;background:#4F46E5;color:#fff;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-size:1rem">Stay Logged In</button>' +
      "</div></div>";

    document.body.appendChild(warningOverlay);

    // Countdown timer
    var countEl = document.getElementById("session-countdown");
    var count = Math.ceil(secondsLeft);
    var countdownInterval = setInterval(function () {
      count--;
      if (countEl) countEl.textContent = count;
      if (count <= 0) {
        clearInterval(countdownInterval);
        redirectToLogin();
      }
    }, 1000);

    warningOverlay._countdownInterval = countdownInterval;
  }

  function dismissWarning() {
    warningShown = false;
    if (warningOverlay) {
      if (warningOverlay._countdownInterval) {
        clearInterval(warningOverlay._countdownInterval);
      }
      warningOverlay.remove();
      warningOverlay = null;
    }
  }

  function redirectToLogin() {
    window.location.href = "/attendance/login.php?timeout=1";
  }

  // Start monitoring
  function init() {
    // Track user activity
    ["mousemove", "keydown", "click", "scroll", "touchstart"].forEach(
      function (evt) {
        document.addEventListener(evt, resetIdleTimer, { passive: true });
      },
    );

    // Periodic heartbeat
    heartbeatTimer = setInterval(sendHeartbeat, HEARTBEAT_INTERVAL);

    // Initial heartbeat
    sendHeartbeat();
  }

  // Only init if user appears to be logged in (check for sidebar or user elements)
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
