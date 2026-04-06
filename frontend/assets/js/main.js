/**
 * SAMS Main JavaScript
 * Core functionality and PWA integration
 */

// PWA Service Worker Registration
if ("serviceWorker" in navigator) {
  window.addEventListener("load", () => {
    navigator.serviceWorker
      .register("/attendance/sw.js", {
        scope: "/attendance/",
      })
      .then((registration) => {
        console.log("[Main] Service Worker registered successfully");

        // Check for updates periodically
        setInterval(() => {
          registration.update();
        }, 60000); // Check every minute
      })
      .catch((error) => {
        console.error("[Main] Service Worker registration failed:", error);
      });
  });
}

// Add manifest to all pages dynamically
function ensureProfessionalTheme() {
  if (document.querySelector("link[data-sams-professional-theme]")) {
    return;
  }

  if (
    Array.from(document.querySelectorAll('link[rel="stylesheet"]')).some(
      (link) => link.href.includes("/assets/css/cyberpunk-ui.css"),
    )
  ) {
    return;
  }

  const hasProfessionalStylesheet = Array.from(
    document.querySelectorAll('link[rel="stylesheet"]'),
  ).some((link) => link.href.includes("/assets/css/professional-ui.css"));

  if (hasProfessionalStylesheet) {
    return;
  }

  const professionalStyles = document.createElement("link");
  professionalStyles.rel = "stylesheet";
  professionalStyles.href = "/attendance/assets/css/professional-ui.css";
  professionalStyles.setAttribute("data-sams-professional-theme", "true");
  document.head.appendChild(professionalStyles);
}

function addManifest() {
  if (!document.querySelector('link[rel="icon"]')) {
    const iconLink = document.createElement("link");
    iconLink.rel = "icon";
    iconLink.type = "image/png";
    iconLink.href = "/attendance/assets/images/icons/logo3.png";
    document.head.appendChild(iconLink);
  }

  if (!document.querySelector('link[rel="shortcut icon"]')) {
    const shortcutIcon = document.createElement("link");
    shortcutIcon.rel = "shortcut icon";
    shortcutIcon.href = "/attendance/assets/images/icons/logo3.png";
    document.head.appendChild(shortcutIcon);
  }

  // Apple touch icon
  if (!document.querySelector('link[rel="apple-touch-icon"]')) {
    const appleTouchIcon = document.createElement("link");
    appleTouchIcon.rel = "apple-touch-icon";
    appleTouchIcon.href = "/attendance/assets/images/icons/logo3.png";
    document.head.appendChild(appleTouchIcon);
  }
  }

  // Apple mobile web app capable
  if (!document.querySelector('meta[name="apple-mobile-web-app-capable"]')) {
    const appCapable = document.createElement("meta");
    appCapable.name = "apple-mobile-web-app-capable";
    appCapable.content = "yes";
    document.head.appendChild(appCapable);
  }

  // Apple status bar style
  if (
    !document.querySelector(
      'meta[name="apple-mobile-web-app-status-bar-style"]',
    )
  ) {
    const statusBar = document.createElement("meta");
    statusBar.name = "apple-mobile-web-app-status-bar-style";
    statusBar.content = "black-translucent";
    document.head.appendChild(statusBar);
  }
}

ensureProfessionalTheme();
addManifest();

// Theme system (global)
const SAMS_THEMES = [
  { id: "light", label: "Light" },
  { id: "dark", label: "Dark" },
];

function getSavedTheme() {
  const saved = localStorage.getItem("sams_theme");
  return SAMS_THEMES.some((theme) => theme.id === saved) ? saved : "light";
}

function applyTheme(themeId) {
  const finalTheme = SAMS_THEMES.some((theme) => theme.id === themeId)
    ? themeId
    : "light";
  document.documentElement.setAttribute("data-theme", finalTheme);
  document.documentElement.classList.toggle("dark", finalTheme === "dark");
  document.documentElement.classList.toggle("light", finalTheme !== "dark");
  localStorage.setItem("sams_theme", finalTheme);
  updateThemeSwitcherSelection(finalTheme);
}

function updateThemeSwitcherSelection(themeId) {
  document.querySelectorAll(".sams-theme-option").forEach((btn) => {
    const isActive = btn.getAttribute("data-theme-id") === themeId;
    btn.classList.toggle("active", isActive);
  });
}

function createThemeSwitcher() {
  if (document.getElementById("samsThemeSwitcher")) return;
  if (!document.body) return;

  const switcher = document.createElement("div");
  switcher.id = "samsThemeSwitcher";
  switcher.className = "sams-theme-switcher";
  switcher.innerHTML = `
        <button class="sams-theme-toggle" id="samsThemeToggle" type="button" title="Theme settings">
            <i class="fas fa-palette"></i>
            <span>Theme</span>
        </button>
        <div class="sams-theme-panel" id="samsThemePanel" hidden>
            <div class="sams-theme-title">Choose Theme</div>
            <div class="sams-theme-options">
                ${SAMS_THEMES.map(
                  (theme) => `
                    <button class="sams-theme-option" type="button" data-theme-id="${theme.id}">
                        ${theme.label}
                    </button>
                `,
                ).join("")}
            </div>
        </div>
    `;

  document.body.appendChild(switcher);

  const toggle = document.getElementById("samsThemeToggle");
  const panel = document.getElementById("samsThemePanel");
  if (toggle && panel) {
    toggle.addEventListener("click", () => {
      panel.hidden = !panel.hidden;
    });

    document.addEventListener("click", (event) => {
      if (!switcher.contains(event.target)) {
        panel.hidden = true;
      }
    });
  }

  document.querySelectorAll(".sams-theme-option").forEach((btn) => {
    btn.addEventListener("click", () => {
      const themeId = btn.getAttribute("data-theme-id");
      applyTheme(themeId);
    });
  });

  applyTheme(getSavedTheme());
}

function applySidebarState() {
  const collapsed = localStorage.getItem("sams_sidebar_collapsed") === "1";
  document.body.classList.toggle("sidebar-collapsed", collapsed);
}

function toggleSidebarCollapse() {
  const collapsed = !document.body.classList.contains("sidebar-collapsed");
  document.body.classList.toggle("sidebar-collapsed", collapsed);
  localStorage.setItem("sams_sidebar_collapsed", collapsed ? "1" : "0");
}

function toggleMobileSidebar() {
  const sidebar = document.querySelector(".sidebar");
  const overlay = document.querySelector(".sidebar-overlay");
  if (!sidebar || !overlay) return;
  sidebar.classList.toggle("active");
  overlay.classList.toggle("active");
}

function closeMobileSidebar() {
  const sidebar = document.querySelector(".sidebar");
  const overlay = document.querySelector(".sidebar-overlay");
  if (!sidebar || !overlay) return;
  sidebar.classList.remove("active");
  overlay.classList.remove("active");
}

// Cyberpunk animations
document.addEventListener("DOMContentLoaded", function () {
  applyTheme(getSavedTheme());
  createThemeSwitcher();
  applySidebarState();

  // Starfield animation
  createStarfield();

  // Glitch effects on hover
  addGlitchEffects();

  // Smooth scrolling
  enableSmoothScroll();

  // Form enhancements
  enhanceForms();
});

function createStarfield() {
  const starfield = document.querySelector(".starfield");
  if (!starfield) return;

  for (let i = 0; i < 100; i++) {
    const star = document.createElement("div");
    star.className = "star";
    star.style.left = Math.random() * 100 + "%";
    star.style.top = Math.random() * 100 + "%";
    star.style.animationDelay = Math.random() * 3 + "s";
    star.style.animationDuration = Math.random() * 3 + 2 + "s";
    starfield.appendChild(star);
  }
}

function addGlitchEffects() {
  const glitchElements = document.querySelectorAll(".glitch-hover");

  glitchElements.forEach((element) => {
    element.addEventListener("mouseenter", function () {
      this.classList.add("glitching");
      setTimeout(() => {
        this.classList.remove("glitching");
      }, 500);
    });
  });
}

function enableSmoothScroll() {
  document.querySelectorAll('a[href^="#"]').forEach((anchor) => {
    anchor.addEventListener("click", function (e) {
      const href = this.getAttribute("href");
      if (href === "#") return;

      e.preventDefault();
      const target = document.querySelector(href);

      if (target) {
        target.scrollIntoView({
          behavior: "smooth",
          block: "start",
        });
      }
    });
  });
}

function enhanceForms() {
  // Add floating labels
  const inputs = document.querySelectorAll("input, textarea, select");

  inputs.forEach((input) => {
    if (input.value) {
      input.classList.add("has-value");
    }

    input.addEventListener("input", function () {
      if (this.value) {
        this.classList.add("has-value");
      } else {
        this.classList.remove("has-value");
      }
    });

    input.addEventListener("focus", function () {
      this.classList.add("focused");
    });

    input.addEventListener("blur", function () {
      this.classList.remove("focused");
    });
  });
}

// Utility functions
function showLoading(show = true) {
  let loader = document.getElementById("global-loader");

  if (!loader && show) {
    loader = document.createElement("div");
    loader.id = "global-loader";
    loader.innerHTML = `
            <div class="loader-content">
                <div class="cyber-loader"></div>
                <p>Loading...</p>
            </div>
        `;
    document.body.appendChild(loader);
  }

  if (loader) {
    loader.style.display = show ? "flex" : "none";
  }
}

function showToast(message, type = "info", duration = 3000) {
  // Use PWA manager toast if available
  if (window.pwaManager) {
    window.pwaManager.showToast(message, type);
    return;
  }

  const toast = document.createElement("div");
  toast.className = `toast toast-${type}`;
  toast.textContent = message;
  document.body.appendChild(toast);

  setTimeout(() => toast.classList.add("show"), 100);

  setTimeout(() => {
    toast.classList.remove("show");
    setTimeout(() => toast.remove(), 300);
  }, duration);
}

function confirmAction(message, callback) {
  const modal = document.createElement("div");
  modal.className = "confirm-modal";
  modal.innerHTML = `
        <div class="confirm-content">
            <h3>Confirm Action</h3>
            <p>${message}</p>
            <div class="confirm-buttons">
                <button class="btn-confirm" onclick="confirmYes()">Confirm</button>
                <button class="btn-cancel" onclick="confirmNo()">Cancel</button>
            </div>
        </div>
    `;

  document.body.appendChild(modal);

  window.confirmYes = function () {
    modal.remove();
    callback(true);
    delete window.confirmYes;
    delete window.confirmNo;
  };

  window.confirmNo = function () {
    modal.remove();
    callback(false);
    delete window.confirmYes;
    delete window.confirmNo;
  };
}

// AJAX helper
async function fetchAPI(endpoint, data = {}, method = "POST") {
  try {
    const options = {
      method: method,
      headers: {
        "Content-Type": "application/json",
      },
    };

    if (method !== "GET") {
      options.body = JSON.stringify(data);
    }

    const response = await fetch(endpoint, options);

    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }

    return await response.json();
  } catch (error) {
    console.error("API fetch error:", error);

    // If offline, queue the request
    if (!navigator.onLine && window.pwaManager) {
      const storeName = getStoreNameFromEndpoint(endpoint);
      if (storeName) {
        await window.pwaManager.queueOfflineAction(storeName, data);
        showToast("Offline - action will sync when connected", "warning");
        return { success: false, offline: true };
      }
    }

    throw error;
  }
}

function getStoreNameFromEndpoint(endpoint) {
  if (endpoint.includes("attendance")) return "pendingAttendance";
  if (endpoint.includes("message")) return "pendingMessages";
  if (endpoint.includes("assignment")) return "pendingSubmissions";
  return null;
}

// Format date/time
function formatDate(dateString, format = "short") {
  const date = new Date(dateString);

  if (format === "short") {
    return date.toLocaleDateString();
  } else if (format === "long") {
    return date.toLocaleDateString("en-US", {
      weekday: "long",
      year: "numeric",
      month: "long",
      day: "numeric",
    });
  } else if (format === "time") {
    return date.toLocaleTimeString("en-US", {
      hour: "2-digit",
      minute: "2-digit",
    });
  } else if (format === "full") {
    return date.toLocaleString();
  }

  return dateString;
}

function formatRelativeTime(dateString) {
  const date = new Date(dateString);
  const now = new Date();
  const diff = now - date;

  const seconds = Math.floor(diff / 1000);
  const minutes = Math.floor(seconds / 60);
  const hours = Math.floor(minutes / 60);
  const days = Math.floor(hours / 24);

  if (seconds < 60) return "Just now";
  if (minutes < 60) return `${minutes}m ago`;
  if (hours < 24) return `${hours}h ago`;
  if (days < 7) return `${days}d ago`;

  return formatDate(dateString, "short");
}

// Copy to clipboard
async function copyToClipboard(text) {
  try {
    await navigator.clipboard.writeText(text);
    showToast("Copied to clipboard!", "success");
  } catch (error) {
    console.error("Copy failed:", error);
    showToast("Failed to copy", "error");
  }
}

// Debounce function
function debounce(func, wait) {
  let timeout;
  return function executedFunction(...args) {
    const later = () => {
      clearTimeout(timeout);
      func(...args);
    };
    clearTimeout(timeout);
    timeout = setTimeout(later, wait);
  };
}

// Throttle function
function throttle(func, limit) {
  let inThrottle;
  return function () {
    const args = arguments;
    const context = this;
    if (!inThrottle) {
      func.apply(context, args);
      inThrottle = true;
      setTimeout(() => (inThrottle = false), limit);
    }
  };
}

// Export for use in other scripts
window.showLoading = showLoading;
window.showToast = showToast;
window.confirmAction = confirmAction;
window.fetchAPI = fetchAPI;
window.formatDate = formatDate;
window.formatRelativeTime = formatRelativeTime;
window.copyToClipboard = copyToClipboard;
window.debounce = debounce;
window.throttle = throttle;
window.applyTheme = applyTheme;
window.toggleSidebarCollapse = toggleSidebarCollapse;
window.toggleMobileSidebar = toggleMobileSidebar;
window.closeMobileSidebar = closeMobileSidebar;

// Global error handler
window.addEventListener("error", function (e) {
  console.error("Global error:", e.error);

  // Log to server if online
  if (navigator.onLine) {
    fetch("/attendance/api/error-log.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        message: e.message,
        stack: e.error?.stack,
        url: window.location.href,
        timestamp: new Date().toISOString(),
      }),
    }).catch(() => {});
  }
});

// Unhandled promise rejections
window.addEventListener("unhandledrejection", function (e) {
  console.error("Unhandled promise rejection:", e.reason);
});

console.log("[Main] SAMS initialized successfully");
