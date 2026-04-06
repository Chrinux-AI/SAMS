/**
 * MCC — Master Control Center JavaScript
 * Real-time dashboard polling, action triggers, event stream.
 */
(function () {
  "use strict";

  const BASE =
    document.querySelector('meta[name="mcc-base"]')?.content ||
    "/attendance/developer/master-control";
  const API = BASE + "/api";
  let refreshInterval = null;
  let eventPollInterval = null;

  // ── Helpers ──
  function scoreColor(score) {
    if (score >= 90) return "var(--mcc-green)";
    if (score >= 70) return "var(--mcc-cyan)";
    if (score >= 50) return "var(--mcc-yellow)";
    return "var(--mcc-red)";
  }

  function scoreClass(score) {
    if (score >= 90) return "good";
    if (score >= 50) return "warn";
    return "bad";
  }

  function statusPillClass(status) {
    if (status === "STABLE") return "stable";
    if (status === "WARNING" || status === "DEGRADED") return "warning";
    return "critical";
  }

  function el(id) {
    return document.getElementById(id);
  }

  function toast(msg, type = "info") {
    let t = document.getElementById("mcc-toast");
    if (!t) {
      t = document.createElement("div");
      t.id = "mcc-toast";
      t.className = "mcc-toast";
      document.body.appendChild(t);
    }
    t.textContent = msg;
    t.className = "mcc-toast " + type + " show";
    setTimeout(() => t.classList.remove("show"), 3500);
  }

  // ── API Calls ──
  async function fetchStatus(section = "all") {
    try {
      const r = await fetch(API + "/system-status.php?section=" + section, {
        credentials: "same-origin",
      });
      const j = await r.json();
      return j.ok ? j.data : null;
    } catch (e) {
      console.error("MCC fetch error:", e);
      return null;
    }
  }

  async function postAction(endpoint, params = {}) {
    const body = new URLSearchParams(params);
    try {
      const r = await fetch(API + "/" + endpoint, {
        method: "POST",
        credentials: "same-origin",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: body,
      });
      return await r.json();
    } catch (e) {
      console.error("MCC action error:", e);
      return { ok: false, error: e.message };
    }
  }

  // ── Render Functions ──
  function renderSystem(d) {
    if (!d) return;
    const pill = el("sys-status-pill");
    if (pill) {
      pill.textContent = d.system;
      pill.className = "mcc-status-pill " + statusPillClass(d.system);
    }
    setText("sys-active-users", d.active_users);
    setText("sys-sessions", d.active_sessions);
    setText("sys-db-latency", d.db_latency_ms + " ms");
    setText("sys-errors", d.errors_last_hour);
    setText("sys-memory", d.memory_mb + " MB");
    setText("sys-php", d.php_version);
    setText("sys-time", d.server_time);

    // OS health score
    const osEl = el("sys-os-health");
    if (osEl) {
      osEl.textContent = d.os_health;
      osEl.style.color = scoreColor(d.os_health);
    }
    // Stability score
    const stabEl = el("sys-stability");
    if (stabEl) {
      stabEl.textContent = d.stability_score;
      stabEl.style.color = scoreColor(d.stability_score);
    }

    colorize(
      "sys-db-latency",
      d.db_latency_ms < 50 ? "good" : d.db_latency_ms < 200 ? "warn" : "bad",
    );
    colorize(
      "sys-errors",
      d.errors_last_hour === 0
        ? "good"
        : d.errors_last_hour < 10
          ? "warn"
          : "bad",
    );
  }

  function renderSecurity(d) {
    if (!d) return;
    setText("sec-failed", d.failed_logins_24h);
    setText("sec-blocked", d.blocked_ips);
    setText("sec-ratelimit", d.rate_limit_hits);
    setText("sec-suspicious", d.suspicious_activity);

    const scoreEl = el("sec-score");
    if (scoreEl) {
      scoreEl.textContent = d.security_score;
      scoreEl.style.color = scoreColor(d.security_score);
    }

    colorize(
      "sec-failed",
      d.failed_logins_24h < 5
        ? "good"
        : d.failed_logins_24h < 20
          ? "warn"
          : "bad",
    );
    colorize("sec-suspicious", d.suspicious_activity === 0 ? "good" : "bad");
  }

  function renderAI(d) {
    if (!d) return;
    setText("ai-active", d.active_count + "/" + d.total_modules);
    setText("ai-cache", d.cache_files + " (" + d.cache_size_kb + " KB)");
    setText("ai-training", d.training_sets + " sets");

    const healthEl = el("ai-health");
    if (healthEl) {
      healthEl.textContent = d.ai_health;
      healthEl.style.color = scoreColor(d.ai_health);
    }

    // Module list
    const list = el("ai-modules-list");
    if (list && d.modules) {
      list.innerHTML = d.modules
        .map(
          (m) =>
            '<div class="mcc-metric"><span class="mcc-metric-label">' +
            esc(m.name) +
            "</span>" +
            '<span class="mcc-badge ' +
            (m.active ? "online" : "offline") +
            '">' +
            esc(m.status) +
            "</span></div>",
        )
        .join("");
    }
  }

  function renderDevOps(d) {
    if (!d) return;
    const scoreEl = el("devops-score");
    if (scoreEl) {
      scoreEl.textContent = d.devops_score || 0;
      scoreEl.style.color = scoreColor(d.devops_score || 0);
    }
    setText("devops-deploy", d.last_deployment || "N/A");
    setText("devops-cron", d.cron_health || "unknown");
    setText("devops-backup", d.backup_status || "unknown");
    setText("devops-fixloop", d.fix_loop_last || "N/A");

    colorize(
      "devops-cron",
      d.cron_health === "healthy"
        ? "good"
        : d.cron_health === "stale"
          ? "warn"
          : "bad",
    );
  }

  function renderDatabase(d) {
    if (!d) return;
    setText("db-tables", d.total_tables);
    setText("db-rows", d.total_rows?.toLocaleString());
    setText("db-size", d.total_size_mb + " MB");
    setText("db-name", d.db_name);

    // Top tables
    const tbody = el("db-table-list");
    if (tbody && d.tables) {
      const top = d.tables.slice(0, 10);
      tbody.innerHTML = top
        .map(
          (t) =>
            "<tr><td>" +
            esc(t.name) +
            "</td><td>" +
            (t.rows || 0).toLocaleString() +
            "</td><td>" +
            t.size_kb +
            " KB</td></tr>",
        )
        .join("");
    }

    // Warnings
    const warnEl = el("db-warnings");
    if (warnEl && d.schema_warnings) {
      setText("db-warn-count", d.schema_warnings.length);
      if (d.schema_warnings.length > 0) {
        warnEl.innerHTML = d.schema_warnings
          .slice(0, 5)
          .map((w) => '<div class="mcc-event warn">' + esc(w) + "</div>")
          .join("");
      } else {
        warnEl.innerHTML = '<div class="mcc-event ok">No schema warnings</div>';
      }
    }
  }

  function renderHealing(d) {
    if (!d) return;
    const scoreEl = el("heal-score");
    if (scoreEl) {
      scoreEl.textContent = d.stability_score || 0;
      scoreEl.style.color = scoreColor(d.stability_score || 0);
    }
    setText("heal-lastrun", d.last_run || "Never");
    setText("heal-repairs", d.repairs_today || 0);
    setText("heal-total", d.total_repairs || 0);

    // Recent repairs
    const stream = el("heal-log");
    if (stream && d.recent_repairs) {
      stream.innerHTML = d.recent_repairs
        .slice(-10)
        .reverse()
        .map((line) => {
          const cls = line.includes("[OK]")
            ? "ok"
            : line.includes("[WARN]")
              ? "warn"
              : line.includes("[ERR]")
                ? "err"
                : "info";
          return '<div class="mcc-event ' + cls + '">' + esc(line) + "</div>";
        })
        .join("");
    }
  }

  function renderInstitution(d) {
    if (!d) return;
    setText("inst-academic", d.academic_mode);
    setText("inst-attendance", d.attendance_window);
    setText("inst-messaging", d.messaging_status);
    setText("inst-ai", d.ai_services);
    setText("inst-users", d.total_users);
    setText("inst-maintenance", d.maintenance_mode ? "ON" : "OFF");

    colorize(
      "inst-messaging",
      d.messaging_status === "ONLINE" ? "good" : "bad",
    );
    colorize(
      "inst-ai",
      d.ai_services === "HEALTHY"
        ? "good"
        : d.ai_services === "PARTIAL"
          ? "warn"
          : "bad",
    );
    colorize("inst-maintenance", d.maintenance_mode ? "bad" : "good");

    // User breakdown
    const breakdown = el("inst-breakdown");
    if (breakdown && d.user_breakdown) {
      breakdown.innerHTML = Object.entries(d.user_breakdown)
        .map(
          ([role, cnt]) =>
            '<div class="mcc-metric"><span class="mcc-metric-label">' +
            esc(role) +
            '</span><span class="mcc-metric-value">' +
            cnt +
            "</span></div>",
        )
        .join("");
    }
  }

  function renderEventStream(allData) {
    const stream = el("live-events");
    if (!stream) return;

    const events = [];

    // Pull events from security
    if (allData.security?.recent_events) {
      allData.security.recent_events.forEach((e) => {
        events.push({
          time: e.created_at || "",
          text:
            (e.event_type || e.action || "event") +
            ": " +
            (e.details || e.description || ""),
          type:
            e.severity === "HIGH" || e.severity === "CRITICAL" ? "err" : "warn",
        });
      });
    }

    // System info
    if (allData.system) {
      events.push({
        time: allData.system.server_time,
        text:
          "System: " +
          allData.system.system +
          " | DB: " +
          allData.system.db_latency_ms +
          "ms | Users: " +
          allData.system.active_users,
        type: allData.system.system === "STABLE" ? "ok" : "warn",
      });
    }

    // Sort by time desc
    events.sort((a, b) => (b.time || "").localeCompare(a.time || ""));

    stream.innerHTML =
      events
        .slice(0, 20)
        .map(
          (e) =>
            '<div class="mcc-event ' +
            e.type +
            '"><span class="mcc-event-time">' +
            esc(e.time?.substring(11, 19) || "--:--:--") +
            "</span>" +
            esc(e.text) +
            "</div>",
        )
        .join("") || '<div class="mcc-event info">No recent events</div>';
  }

  // ── Utility ──
  function setText(id, val) {
    const e = el(id);
    if (e) e.textContent = val ?? "--";
  }

  function colorize(id, cls) {
    const e = el(id);
    if (e) {
      e.classList.remove("good", "warn", "bad");
      e.classList.add(cls);
    }
  }

  function esc(s) {
    const d = document.createElement("div");
    d.textContent = s || "";
    return d.innerHTML;
  }

  // ── Full Refresh ──
  async function fullRefresh() {
    const data = await fetchStatus("all");
    if (!data) return;

    renderSystem(data.system);
    renderSecurity(data.security);
    renderAI(data.ai);
    renderDevOps(data.devops);
    renderDatabase(data.database);
    renderHealing(data.healing);
    renderInstitution(data.institution);
    renderEventStream(data);

    // Update last refresh timestamp
    setText("last-refresh", new Date().toLocaleTimeString());
  }

  // ── Action Handlers ──
  window.mccAction = async function (endpoint, params, btnEl) {
    if (btnEl) {
      btnEl.disabled = true;
      btnEl.textContent = "Running...";
    }
    const result = await postAction(endpoint, params);
    if (btnEl) {
      btnEl.disabled = false;
      btnEl.textContent = btnEl.dataset.label || "Done";
    }

    if (result.ok) {
      toast(
        params.action || params.service || params.target || "Action completed",
        "success",
      );
      setTimeout(fullRefresh, 1000);
    } else {
      toast("Error: " + (result.error || "Unknown"), "error");
    }
    return result;
  };

  window.mccEmergency = async function (action) {
    if (action === "activate") {
      if (
        !confirm(
          "⚠️ EMERGENCY LOCKDOWN will:\n\n• Disable all logins\n• Force logout all users\n• Enable maintenance mode\n\nContinue?",
        )
      )
        return;
    }
    const result = await postAction("emergency-lockdown.php", {
      action: action,
    });
    if (result.ok) {
      toast(
        "Lockdown: " + (result.status || action),
        action === "activate" ? "error" : "success",
      );
      setTimeout(fullRefresh, 1000);
    } else {
      toast("Lockdown error: " + (result.error || "Unknown"), "error");
    }
  };

  // ── Init ──
  document.addEventListener("DOMContentLoaded", function () {
    fullRefresh();
    refreshInterval = setInterval(fullRefresh, 5000);
  });
})();
