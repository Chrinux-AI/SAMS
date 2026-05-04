# Copilot Instructions For This Repo

Read `CURRENT_PHASE_STATUS.md` before editing code.

This repository is in launch scope freeze.

## Copilot Rules

- do not add new product features
- do not redesign the UI
- do not refactor broadly
- make small, local fixes only
- validate every touched PHP file with `php -l`
- for launch-facing pages, confirm the page live in a separate local session
- do not use the user's in-app browser session
- do not revert unrelated dirty files

## Current Priority

Stabilize launch-facing backend/runtime behavior and normalize legacy role pages that use the shared sidebar with old CSS.

## Start Every Session By Stating

1. current phase status
2. files you intend to touch
3. validation you will run before stopping
