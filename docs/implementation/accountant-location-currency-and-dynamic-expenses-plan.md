# Accountant: Location-Based Currency + Dynamic Expense Data Plan

## Goal
- Format currency based on tenant/location context, not hardcoded symbols.
- Remove static expense/dashboard placeholders and compute values from live DB records.

## Implementation Steps
1. Add shared frontend currency helpers:
   - Resolve tenant locale/currency by checking `school_tenants` columns when available (`currency_code`, `currency_symbol`, `country`, `locale`, `country_code`).
   - Fallback defaults: `en-NG` + `NGN`.
   - Add a formatter function that returns locale-aware currency strings with safe fallbacks.
2. Update accountant `dashboard.php`:
   - Replace local `format_naira` usage with shared location-aware formatter.
   - Replace static expense breakdown percentages with category totals from DB (`expenses`) for current tenant.
   - Replace static budget utilization rows with top live categories and percentages.
   - Replace static overdue card amounts/counts with DB-backed unpaid fee and pending-approval metrics.
3. Update accountant `expenses.php`:
   - Replace `$` amount rendering with location-aware formatter.
   - Compute month-over-month expense change using current vs previous month totals.
   - Replace static status text (“Q3 overhead”) with DB-driven summary text.
   - Replace fixed mini-bars with dynamic values based on approved/pending/rejected distribution.
   - Replace static category select options with categories derived from live expense records.
4. Validate edited PHP files for syntax/errors.

## Notes
- Preserve tenant scoping (`tenant_id`) for all queries.
- Avoid assumptions if source tables/columns are missing; gracefully fallback to neutral labels and zeroes.
