/**
 * SAMS Theme System — Standalone JavaScript Module
 *
 * Implements light/dark theme toggle functionality for dashboards.
 * Works with all dashboard roles via configuration.
 *
 * Features:
 * - Light/Dark theme switching
 * - localStorage persistence (dual key compatibility)
 * - System preference fallback
 * - Dropdown menu management
 * - Keyboard navigation (Escape key)
 * - Mobile responsive
 *
 * Usage in dashboard (at bottom of <body>):
 *
 *   <script src="/attendance/frontend/includes/theme-system.js"></script>
 *   <script>
 *     initTheme({
 *       role: 'admin',          // Dashboard role
 *       storageKeys: ['sams_theme', 'sams-theme'],
 *       defaultTheme: 'light'
 *     });
 *   </script>
 */

function initTheme(config) {
  // Configuration with defaults
  const defaults = {
    role: "general",
    storageKeys: ["sams_theme", "sams-theme"],
    defaultTheme: "light",
    autoCloseMobileMenu: true,
  };

  const settings = Object.assign({}, defaults, config || {});
  const ROLE = settings.role;
  const STORAGE_KEYS = settings.storageKeys;
  const DEFAULT_THEME = settings.defaultTheme;

  // Get toolbar and early exit if not found
  const toolbar = document.querySelector("[data-" + ROLE + "-toolbar]");
  if (!toolbar) {
    console.warn("[Theme System] Toolbar not found for role:", ROLE);
    return;
  }

  // Query DOM elements
  const toggles = Array.from(
    toolbar.querySelectorAll("[data-" + ROLE + "-dropdown-toggle]"),
  );
  const menus = Array.from(
    toolbar.querySelectorAll("[data-" + ROLE + "-dropdown-menu]"),
  );
  const themeChoices = Array.from(
    toolbar.querySelectorAll("[data-" + ROLE + "-theme-choice]"),
  );
  const themeIcon = toolbar.querySelector("[data-" + ROLE + "-theme-icon]");

  // Normalize theme value
  const normalizeTheme = (theme) => {
    return theme === "dark" ? "dark" : "light";
  };

  // Read theme from storage or system preference
  const readTheme = () => {
    // Try storage keys in order
    try {
      for (const key of STORAGE_KEYS) {
        const stored = localStorage.getItem(key);
        if (stored) {
          return normalizeTheme(stored);
        }
      }
    } catch (error) {
      console.warn("[Theme System] localStorage read failed:", error);
    }

    // Fall back to system preference
    if (
      window.matchMedia &&
      window.matchMedia("(prefers-color-scheme: dark)").matches
    ) {
      return "dark";
    }

    return DEFAULT_THEME;
  };

  // Persist theme to storage
  const persistTheme = (theme) => {
    try {
      const normalized = normalizeTheme(theme);
      for (const key of STORAGE_KEYS) {
        localStorage.setItem(key, normalized);
      }
    } catch (error) {
      console.warn("[Theme System] localStorage write failed:", error);
    }
  };

  // Update theme button states
  const updateThemeButtons = (theme) => {
    themeChoices.forEach((button) => {
      const choice = button.getAttribute("data-" + ROLE + "-theme-choice");
      const isSelected = choice === theme;

      // Update button styling
      button.classList.toggle("bg-primary-container", isSelected);
      button.classList.toggle("text-on-primary-container", isSelected);
      button.classList.toggle("text-on-surface", !isSelected);

      // Update checkmark visibility
      const checkmark = button.querySelector("[data-" + ROLE + "-theme-check]");
      if (checkmark) {
        checkmark.classList.toggle("hidden", !isSelected);
      }
    });

    // Update icon
    if (themeIcon) {
      themeIcon.textContent = theme === "dark" ? "dark_mode" : "light_mode";
    }
  };

  // Apply theme and update UI
  const applyTheme = (theme) => {
    const normalized = normalizeTheme(theme);

    // Update DOM classes
    document.documentElement.setAttribute("data-theme", normalized);
    document.documentElement.classList.toggle("dark", normalized === "dark");
    document.documentElement.classList.toggle("light", normalized !== "dark");

    // Persist and update UI
    persistTheme(normalized);
    updateThemeButtons(normalized);
  };

  // Close all menus
  const closeMenus = () => {
    toggles.forEach((toggle) => {
      toggle.setAttribute("aria-expanded", "false");
    });
    menus.forEach((menu) => {
      menu.classList.add("hidden");
    });
  };

  // Open specific menu
  const openMenu = (menuId) => {
    const menu = document.getElementById(menuId);
    if (!menu) return;

    const isHidden = menu.classList.contains("hidden");
    closeMenus();

    if (isHidden) {
      menu.classList.remove("hidden");
      const toggle = toolbar.querySelector(
        "[data-" + ROLE + '-dropdown-toggle="' + menuId + '"]',
      );
      if (toggle) {
        toggle.setAttribute("aria-expanded", "true");
      }
    }
  };

  // Event listeners: Toggle buttons
  toggles.forEach((toggle) => {
    toggle.addEventListener("click", (event) => {
      event.preventDefault();
      event.stopPropagation();
      const menuId = toggle.getAttribute("data-" + ROLE + "-dropdown-toggle");
      openMenu(menuId);
    });
  });

  // Event listeners: Theme choice buttons
  themeChoices.forEach((choice) => {
    choice.addEventListener("click", (event) => {
      event.preventDefault();
      event.stopPropagation();
      const selectedTheme =
        choice.getAttribute("data-" + ROLE + "-theme-choice") || DEFAULT_THEME;
      applyTheme(selectedTheme);
      closeMenus();
    });
  });

  // Event listener: Outside clicks close menus
  document.addEventListener("click", (event) => {
    if (!toolbar.contains(event.target)) {
      closeMenus();
    }
  });

  // Event listener: Escape key closes menus
  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") {
      closeMenus();
    }
  });

  // Initialize theme from storage/system preference
  applyTheme(readTheme());
}

// If loaded as module, support both script tag and module import
if (typeof module !== "undefined" && module.exports) {
  module.exports = { initTheme };
}
