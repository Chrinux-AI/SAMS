# SAMS Phase 1: Theme Toggle Utilities — Complete Reference

**Status**: ✅ **COMPLETE**
**Date**: April 6, 2026
**Phase**: 1 of 4 (Core Reusable Utilities)

---

## Summary

Three core reusable utilities have been created for SAMS theme management. These provide a foundation for adding light/dark theme toggles to all dashboard roles.

### Files Created

| File                                          | Type       | Purpose                                                          |
| --------------------------------------------- | ---------- | ---------------------------------------------------------------- |
| `frontend/includes/theme-manager.php`         | PHP        | Tailwind config, favicon injection, theme initialization helpers |
| `frontend/includes/theme-navbar-template.php` | HTML/PHP   | Reusable theme toggle UI menu template                           |
| `frontend/includes/theme-system.js`           | JavaScript | Standalone theme switching module                                |

---

## 1. theme-manager.php

**Location**: `frontend/includes/theme-manager.php`

**Purpose**: Centralized PHP utility for theme configuration and helpers.

### Functions Available

#### `themeGetTailwindConfig()`

Returns associative array with SAMS Tailwind configuration.

```php
$config = themeGetTailwindConfig();
// Returns array with darkMode, theme, colors, fonts, etc.
```

#### `themeInjectTailwindConfig()`

Outputs complete `<script>` tag with Tailwind configuration for dashboard `<head>`.

```php
<?php themeInjectTailwindConfig(); ?>
<!-- Outputs:
<script>
  tailwind.config = {
    darkMode: "class",
    theme: { extend: { colors: {...}, ... } }
  };
</script>
-->
```

#### `themeGetFaviconMeta($basePath)`

Returns favicon meta tags HTML string.

```php
$html = themeGetFaviconMeta('/attendance/assets/images/icons/');
// Returns HTML with <link> tags for favicon, apple-touch-icon, etc.
```

#### `themeInjectFaviconMeta($basePath)`

Directly outputs favicon meta tags (wrapper around `themeGetFaviconMeta()`).

```php
<?php themeInjectFaviconMeta(); ?>
```

#### `themeGetInitScript($role, $options)`

Returns `<script>` tag with JavaScript initialization code.

**Parameters:**

- `$role` (string): Dashboard role (e.g., 'admin', 'student', 'teacher')
- `$options` (array): Configuration options
  - `storageKeys` (array): localStorage keys to check
  - `defaultTheme` (string): Theme if no preference found
  - `autoCloseMobileMenu` (bool): Auto-close menus on selection

```php
<?php themeGetInitScript('admin', ['defaultTheme' => 'light']); ?>
```

### Colors Provided

All colors are Material Design 3 compliant:

**Primary**: `#1868DB`
**Secondary**: `#545F71`
**Tertiary**: `#8F4C00`
**Error**: `#BA1A1A`

**Surface shades**: `surface`, `surface-container-low`, `surface-container`, `surface-container-high`, `surface-container-highest`

**On colors**: `on-primary`, `on-secondary`, `on-tertiary`, `on-error`, `on-surface`, `on-background`

---

## 2. theme-navbar-template.php

**Location**: `frontend/includes/theme-navbar-template.php`

**Purpose**: Reusable HTML fragment for theme toggle menu UI.

### Usage

Requires `$role` variable to be set. Include in toolbar/header:

```php
<?php
  $role = 'admin'; // Set to dashboard role
  include __DIR__ . '/theme-navbar-template.php';
?>
```

### Output

Generates:

1. **Theme toggle button** with icon (in toolbar)
2. **Theme dropdown menu** with Light/Dark options

### Requirements

- Assumes `data-{role}-toolbar` element exists (e.g., `data-admin-toolbar`)
- Material Icons must be loaded (uses `light_mode`, `dark_mode`, `check` icons)
- Tailwind CSS must be active with theme colors loaded

### Customization

Edit `$role`, `$show_icon`, `$icon_position` variables before inclusion:

```php
<?php
  $role = 'student';
  $show_icon = true;              // Show/hide icon
  $icon_position = 'right';       // 'left' or 'right'
  include __DIR__ . '/theme-navbar-template.php';
?>
```

---

## 3. theme-system.js

**Location**: `frontend/includes/theme-system.js`

**Purpose**: Standalone JavaScript module for theme switching logic.

### Usage

Include script and call initialization:

```html
<script src="/attendance/frontend/includes/theme-system.js"></script>
<script>
  initTheme({
    role: "admin",
    storageKeys: ["sams_theme", "sams-theme"],
    defaultTheme: "light",
  });
</script>
```

### Configuration

```javascript
initTheme({
  role: "admin", // REQUIRED: Dashboard role
  storageKeys: ["sams_theme", "sams-theme"], // localStorage keys to check/set
  defaultTheme: "light", // Fallback if no preference
  autoCloseMobileMenu: true, // Close menu on theme select
});
```

### How It Works

1. **Reads** theme from localStorage (tries each key in order)
2. **Falls back** to system preference if available (`prefers-color-scheme: dark`)
3. **Defaults** to specified theme if nothing found
4. **Applies** theme by toggling `dark` / `light` classes on `<html>` element
5. **Persists** choice to localStorage for next visit
6. **Manages** theme toggle menu (open/close with Escape key and outside clicks)

### DOM Requirements

The script expects these data attributes in your toolbar:

```html
<!-- Toolbar container -->
<div data-{role}-toolbar>
  <!-- Toggle button -->
  <button data-{role}-dropdown-toggle="{role}-theme-menu">
    <span data-{role}-theme-icon>light_mode</span>
  </button>

  <!-- Theme menu -->
  <div id="{role}-theme-menu" data-{role}-dropdown-menu>
    <!-- Light option -->
    <button data-{role}-theme-choice="light">
      <span data-{role}-theme-check>check</span>
    </button>

    <!-- Dark option -->
    <button data-{role}-theme-choice="dark">
      <span data-{role}-theme-check>check</span>
    </button>
  </div>
</div>
```

### Keyboard Navigation

- **Escape**: Close open menus
- **Click outside menu**: Close menu

---

## Integration Guide

### For Dashboard Developers

To add theme toggle to any dashboard (e.g., `frontend/student/dashboard.php`):

#### Step 1: Add to `<head>` (after Tailwind)

```php
<head>
  <!-- ... other head content ... -->

  <!-- Tailwind CDN -->
  <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>

  <!-- SAMS Theme Manager -->
  <?php
    require __DIR__ . '/../includes/theme-manager.php';
    themeInjectTailwindConfig();
    themeInjectFaviconMeta();
  ?>
</head>
```

#### Step 2: Add HTML tag classes

```php
<html class="light" lang="en">
```

#### Step 3: Add theme toggle button in toolbar

```php
<div data-student-toolbar>
  <!-- Other toolbar items... -->

  <!-- Theme Toggle -->
  <?php
    $role = 'student';
    include __DIR__ . '/../includes/theme-navbar-template.php';
  ?>
</div>
```

#### Step 4: Initialize JavaScript at bottom of `<body>`

```php
<body>
  <!-- Page content... -->

  <!-- Initialize Theme System -->
  <script src="<?php echo $_layout_depth ?? '../'; ?>includes/theme-system.js"></script>
  <script>
    initTheme({ role: 'student' });
  </script>
</body>
```

---

## Color Palette Reference

### Light Mode (Default)

| Token                  | Color   | Usage                      |
| ---------------------- | ------- | -------------------------- |
| `primary`              | #1868DB | Buttons, links, highlights |
| `primary-container`    | #D6E4FF | Light backgrounds          |
| `on-primary-container` | #001B3D | Text on light backgrounds  |
| `secondary`            | #545F71 | Secondary actions          |
| `surface`              | #FDFBFF | Main background            |
| `surface-container`    | #F1F4FA | Card/section backgrounds   |
| `on-surface`           | #1A1C1E | Main text                  |
| `outline`              | #73777F | Borders                    |

### Dark Mode

Tailwind's `dark:` prefix automatically applies inverse colors:

```html
<!-- Light only -->
<div class="bg-surface">Light background</div>

<!-- Automatically dark in dark mode -->
<div class="dark:bg-dark-surface">Auto adjusts</div>

<!-- Explicit dark variant -->
<div class="bg-white dark:bg-slate-900">Light/dark</div>
```

---

## Favicon Strategy

All favicons now use existing logo files:

- **Light Mode**: `logo3.png`
- **Dark Mode**: `logo2.png` (auto-detected via `prefers-color-scheme`)
- **Apple Touch**: `logo3.png` (light always, as it's on-device)

Files are served from: `/attendance/assets/images/icons/`

---

## Browser Support

- ✅ Chrome 76+ (CSS `dark` class)
- ✅ Firefox 67+ (CSS `dark` class)
- ✅ Safari 12.1+
- ✅ Edge 79+
- ✅ Mobile browsers (iOS Safari 13+, Chrome Mobile)

---

## localStorage Keys

The theme system checks these keys in order (for compatibility):

1. `sams_theme`
2. `sams-theme`

Both are set on every theme change to maintain backwards compatibility.

---

## Next Phase (Phase 2)

Phase 2 will apply these utilities to all 12 role dashboards:

- Admin, Student, Teacher, Staff, Principal, Parent
- Owner, Nurse, Librarian, Forum Moderator, Bursar, Transport

Each dashboard will follow the integration guide above to add theme toggle functionality.

---

## Troubleshooting

### Theme not persisting

- Check browser allows localStorage
- Verify `storageKeys` configuration
- Test in non-private browsing mode

### Icons not showing

- Verify Material Icons font is loaded: `fonts.googleapis.com/css2?family=Material+Symbols+Outlined`
- Check icon names: `light_mode`, `dark_mode`, `check`

### Dark mode not applying

- Ensure `darkMode: "class"` is in Tailwind config
- Verify `<html class="dark">` is set correctly
- Check Tailwind build includes all variant generators

### Menu not closing

- Verify `data-{role}-dropdown-menu` elements have `hidden` class initially
- Check `data-{role}-dropdown-toggle` button has `aria-controls` attribute
- Ensure JavaScript initializes after DOM is ready

---

## Related Files

- Documentation: `docs/THEME_TOGGLE_IMPLEMENTATION_PLAN.md`
- Accountant Dashboard (Reference): `frontend/accountant/dashboard.php`
- Master Layout: `frontend/resources/ui-core/layouts/master-dashboard.php`

---

**Created**: April 6, 2026
**Status**: Ready for Phase 2 adoption
**Maintainer**: SAMS Development Team
