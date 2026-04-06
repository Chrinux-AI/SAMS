# Theme Toggle Implementation Plan

## Overview

Standardize and implement light/dark theme toggle functionality across all role-based dashboards in the SAMS application. The accountant dashboard already has a working implementation that will serve as the reference.

---

## Scope

### Current State

- ✅ **Accountant Dashboard** (`frontend/accountant/dashboard.php`): Fully implemented with light/dark toggle
- ❌ **Other Dashboards** (11 roles): No theme toggle functionality
  - Admin, Student, Teacher, Staff, Principal, Parent, Owner, Nurse, Librarian, Forum Moderator, Bursar, Transport

### Deliverables

1. **Reference Documentation**: Standardized pattern for theme toggle implementation
2. **Core Theme Utility**: Reusable JavaScript module for theme management
3. **CSS Template**: Consistent Tailwind dark mode configuration
4. **Dashboard Updates**: Add theme toggles to all 11 remaining dashboards
5. **Testing Guide**: Validation checklist for functionality

---

## Technical Architecture

### Theme Storage Strategy

- **Primary**: `localStorage.setItem('sams_theme', value)` and `localStorage.setItem('sams-theme', value)` (dual keys for compatibility)
- **Fallback**: System preference via `window.matchMedia('(prefers-color-scheme: dark)')`
- **Default**: 'light' mode if no preference detected

### DOM Implementation

- **Root Element**: `<html>` tag with `class="light"` or `class="dark"`
- **Data Attribute**: Optional `data-theme="dark|light"` for debugging
- **Tailwind Config**: `darkMode: "class"` in `tailwind.config`

### Theme Toggle UI Pattern

**Markup Structure:**

```html
<button data-{role}-dropdown-toggle="theme-menu" aria-controls="theme-menu">
  <span class="material-symbols-outlined" data-{role}-theme-icon
    >light_mode</span
  >
</button>

<div id="theme-menu" data-{role}-dropdown-menu>
  <button data-{role}-theme-choice="light">
    <span class="material-symbols-outlined">light_mode</span>
    Light mode
    <span data-{role}-theme-check hidden>check</span>
  </button>
  <button data-{role}-theme-choice="dark">
    <span class="material-symbols-outlined">dark_mode</span>
    Dark mode
    <span data-{role}-theme-check hidden>check</span>
  </button>
</div>
```

**JavaScript Pattern:**

```javascript
// IIFE that encapsulates theme logic
(() => {
  const toolbar = document.querySelector('[data-{role}-toolbar]');

  // Reading/Writing
  const readTheme = () => { /* localStorage → matchMedia → default */ }
  const persistTheme = (theme) => { /* localStorage.set */ }

  // UI Updates
  const updateThemeButtons = (theme) => { /* show checkmark */ }
  const applyTheme = (theme) => { /* classList.toggle + persist */ }

  // Event Handling
  const closeMenus = () => { /* hide all menus */ }
  const openMenu = (menuId) => { /* show specific menu */ }

  // Event Listeners
  toggleButtons.forEach(btn => btn.addEventListener('click', ...))
  themeChoices.forEach(choice => choice.addEventListener('click', ...))
  document.addEventListener('click', closeMenus)
  document.addEventListener('keydown', handleEscape)

  // Initialize
  applyTheme(readTheme())
})();
```

---

## Implementation Steps

### Phase 1: Core Utilities (Foundation)

**Goal**: Create reusable theme management code

1. **Create `frontend/includes/theme-manager.php`**
   - PHP helper function to inject Tailwind dark mode config
   - Returns consistent color palette object for all roles
   - Includes shared CSS boilerplate

2. **Create `frontend/includes/theme-navbar-template.php`**
   - Reusable HTML template for theme menu
   - Accepts `$role` parameter for data attribute naming
   - **Parameters**: `$role` (string), `$show_theme_icon` (bool)
   - **Output**: Dropdown menu HTML fragment + theme icon button

3. **Create `frontend/includes/theme-script.js`** (or inline)
   - Reusable JavaScript module/IIFE
   - Accepts configuration object with role name
   - **Config options**:
     - `role`: 'admin', 'student', 'teacher', etc.
     - `storageKeys`: ['sams_theme', 'sams-theme']
     - `defaultTheme`: 'light'

### Phase 2: Tailwind Configuration Sync

**Goal**: Ensure consistent color scheme across all dashboards

- Review accountant dashboard Tailwind config colors
- Document color palette (primary, secondary, surface, etc.)
- Add reference to `**/*.php` dashboards or centralize in `<head>` snippet

### Phase 3: Apply to All Dashboards

**Goal**: Add theme toggle to each dashboard following the pattern

**For each dashboard (`frontend/{role}/dashboard.php`):**

1. **Update HTML tag**:

   ```php
   <html class="light" lang="en">
   ```

2. **Include Tailwind config** in `<head>`:

   ```javascript
   <script>
     tailwind.config = { darkMode: "class", theme: { /* shared colors */ } };
   </script>
   ```

3. **Add theme toggle button** in toolbar (near notifications/user menu):

   ```php
   <?php include __DIR__ . '/../includes/theme-navbar-template.php'; ?>
   ```

4. **Initialize JavaScript** at bottom of `<body>`:
   ```javascript
   <script src="/frontend/includes/theme-script.js"></script>
   <script>
     initTheme({ role: '<?php echo $role; ?>' });
   </script>
   ```

### Phase 4: Testing & Validation

**For each dashboard:**

- ✓ Theme toggle button visible and clickable
- ✓ Light mode: correct colors applied
- ✓ Dark mode: Tailwind `dark:` classes activate
- ✓ Selection persists across page refreshes
- ✓ System preference respected on first visit
- ✓ Escape key closes menu
- ✓ Outside click closes menu
- ✓ Checkmark shows selected theme
- ✓ Icon updates to reflect current theme
- ✓ Mobile responsive

---

## File Changes Summary

| File                                          | Action     | Notes                                  |
| --------------------------------------------- | ---------- | -------------------------------------- |
| `frontend/includes/theme-manager.php`         | **Create** | Shared PHP helpers for theme           |
| `frontend/includes/theme-navbar-template.php` | **Create** | Reusable HTML menu template            |
| `frontend/includes/theme-script.js`           | **Create** | Reusable JavaScript module             |
| `frontend/admin/dashboard.php`                | **Update** | Add theme to admin dashboard           |
| `frontend/student/dashboard.php`              | **Update** | Add theme to student dashboard         |
| `frontend/teacher/dashboard.php`              | **Update** | Add theme to teacher dashboard         |
| `frontend/staff/dashboard.php`                | **Update** | Add theme to staff dashboard           |
| `frontend/principal/dashboard.php`            | **Update** | Add theme to principal dashboard       |
| `frontend/parent/dashboard.php`               | **Update** | Add theme to parent dashboard          |
| `frontend/owner/dashboard.php`                | **Update** | Add theme to owner dashboard           |
| `frontend/nurse/dashboard.php`                | **Update** | Add theme to nurse dashboard           |
| `frontend/librarian/dashboard.php`            | **Update** | Add theme to librarian dashboard       |
| `frontend/forum-moderator/dashboard.php`      | **Update** | Add theme to forum moderator dashboard |
| `frontend/bursar/dashboard.php`               | **Update** | Add theme to bursar dashboard          |
| `frontend/transport/dashboard.php`            | **Update** | Add theme to transport dashboard       |
| `frontend/accountant/dashboard.php`           | **Review** | Already has theme; verify pattern      |
| `docs/THEME_IMPLEMENTATION_REFERENCE.md`      | **Create** | Complete technical reference           |

---

## Implementation Breakdown

### Total Dashboards to Update: 12

- Admin, Student, Teacher, Staff, Principal, Parent, Owner, Nurse, Librarian, Forum Moderator, Bursar, Transport

### Estimated Effort

- **Phase 1** (Core): 1-2 hours (create 3 utilities)
- **Phase 2** (Config): 30 minutes (review & document)
- **Phase 3** (Board): 2-3 hours (12 dashboards × ~10-15 min each)
- **Phase 4** (Testing): 1 hour (12 dashboards × ~5 min each)
- **Total**: ~5-6.5 hours

---

## Code Reusability Checklist

- ✓ No hard-coded role names in utilities
- ✓ Configuration object for customization
- ✓ Fallback for missing localStorage
- ✓ Consistent CSS class naming (`data-{role}-*`)
- ✓ Theme icon auto-updates
- ✓ Works without external theme library
- ✓ Mobile responsive menu
- ✓ Keyboard accessible (Escape, click outside)
- ✓ ARIA labels for screen readers

---

## Risk Mitigation

| Risk                       | Mitigation                                                  |
| -------------------------- | ----------------------------------------------------------- |
| Tailwind config conflicts  | Centralize color palette in utility; document in README     |
| localStorage not available | Graceful fallback to matchMedia; test in private mode       |
| Menu race conditions       | Close all menus before opening one; debounce clicks         |
| Mobile UI overlap          | Test on multiple screen sizes; adjust z-index if needed     |
| Dark mode readability      | Use accountant dashboard colors as reference; test contrast |

---

## Success Criteria

1. ✓ All 12 dashboards have working light/dark toggles
2. ✓ Theme choice persists across sessions
3. ✓ System preference respected on first visit
4. ✓ Consistent UI/UX across all roles
5. ✓ No styling conflicts or regressions
6. ✓ Mobile responsive
7. ✓ A11y compliant (ARIA labels, keyboard nav)
8. ✓ Performance: <50ms theme switch

---

## Next Steps

1. **Review** this plan with stakeholders
2. **Approval** to proceed with Phase 1
3. **Implement** core utilities
4. **Apply** to all dashboards
5. **Test** each role's dashboard
6. **Document** final implementation pattern for future maintenance
