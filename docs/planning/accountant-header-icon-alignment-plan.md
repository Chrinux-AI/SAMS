# Accountant Header Icon Alignment Plan

## Goal

Make the accountant role use the same top-right header action placement for notifications, settings, more-actions, and profile/avatar across all accountant screens.

## Scope

- Update the shared accountant shell used by most accountant pages.
- Update the standalone accountant dashboard header so it matches the same action cluster.
- Keep the change lightweight and consistent with the existing Atlas-style UI.

## Steps

1. Review the existing accountant header patterns in `frontend/accountant/partials/atlas-shell.php` and `frontend/accountant/dashboard.php`.
2. Add a consistent top-right action group with notifications, settings, more-actions, and profile/avatar placement.
3. Validate that accountant pages using the shared shell inherit the new header without extra page-by-page work.
4. Run a quick error check to confirm the touched files remain clean.

## Notes

- Prefer a single shared pattern so future accountant pages reuse the same header layout.
- Keep the visual treatment aligned with the current Material Symbols + Atlas styling.
