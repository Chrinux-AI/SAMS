# SAMS Agent Rules

This repository is in launch scope freeze.

Read `CURRENT_PHASE_STATUS.md` before making any edit.

## Required Behavior

Before proceeding, every AI agent must:

1. summarize the current project status in one short paragraph
2. state the exact files it plans to touch
3. validate every touched PHP file with `php -l`
4. live-test launch-facing pages with separate local sessions only

## Do

- make small, local fixes
- preserve existing behavior unless there is a real runtime, tenant-scope, permission, or UI consistency issue
- prefer existing helpers and existing layouts
- work inside `C:\\xampp\\htdocs\\attendance`
- keep updates in `CURRENT_PHASE_STATUS.md` and the relevant brief files truthful and current

## Do Not

- do not add new features
- do not redesign the product
- do not refactor broadly
- do not use the user's in-app browser session
- do not revert unrelated dirty worktree changes
- do not assume one schema shape when helper-based fallback is available

## Current Known UI Failure Pattern

Watch for legacy pages that use:

- `professional-ui.css`
- shared `sidebar-nav.php`
- missing `../assets/css/sams-core.css`
- missing Material Symbols font
- stray empty `<div class="app-layout"></div>`
- nested `app-layout` wrappers as content containers

Fix those pages conservatively.

## Local Validation Defaults

- Base URL: `http://127.0.0.1:8001/attendance`
- Dev password: `DevPass@2026`
- Refresh dev accounts: `php backend/scripts/utilities/ensure-dev-logins.php`

## Agent Coordination

- GitHub Copilot should read `docs/planning/GITHUB_COPILOT_BRIEF.md`
- Antigravity should read `docs/planning/ANTIGRAVITY_BRIEF.md`
- Any general-purpose agent should read this file plus `CURRENT_PHASE_STATUS.md`
