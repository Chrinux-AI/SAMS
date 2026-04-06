# Design System Strategy: The Financial Architect

## 1. Overview & Creative North Star
The "Financial Architect" is our Creative North Star. School management requires more than just a ledger; it requires a sense of institutional stability and absolute clarity. We move away from the "busy dashboard" trope toward an **Editorial Financial Ledger**—a style that treats data with the same reverence as a high-end broadsheet or a premium banking report.

We break the "template" look by rejecting the rigid grid in favor of **intentional whitespace and tonal layering**. By using high-contrast typography scales and removing 1px borders, we create a fluid, sophisticated environment where the data breathes and the most critical financial metrics take center stage.

---

## 2. Colors: Tonal Integrity
We utilize a sophisticated Material Design palette that prioritizes depth over decoration.

### The "No-Line" Rule
**Explicit Instruction:** Do not use 1px solid borders to section off content. Boundaries must be defined solely through background color shifts. A `surface-container-low` section sitting on a `surface` background is our primary method of containment.

### Surface Hierarchy & Nesting
Treat the UI as a series of stacked sheets of fine paper.
- **Base Layer:** `surface` (The foundation).
- **Secondary Sectioning:** `surface-container-low` (Subtle grouping).
- **Primary Interactives/Cards:** `surface-container-lowest` (The "lifted" paper look).
- **High-Focus Overlays:** `surface-container-highest` (Used for modals or sticky headers).

### The "Glass & Gradient" Rule
To elevate the "Accountant" module from a tool to a premium experience:
- **Glassmorphism:** Use `backdrop-blur-xl` with semi-transparent `surface` colors for floating action bars or the sidebar navigation.
- **Signature Textures:** For high-level financial health cards (Net Profit), use a subtle linear gradient from `primary` to `primary_container` at a 15-degree angle. This provides a "soul" to the data that flat hex codes cannot achieve.

---

## 3. Typography: The Editorial Scale
We pair **Manrope** (Display/Headlines) with **Inter** (Data/UI). Manrope provides a modern, geometric authority, while Inter’s high x-height ensures financial tables remain legible even at small sizes.

| Level | Token | Font | Size | Weight | Use Case |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **Display** | `display-md` | Manrope | 2.75rem | 700 | Total Revenue / Hero Figures |
| **Headline** | `headline-sm` | Manrope | 1.5rem | 600 | Page Titles (e.g., "Ledger Overview") |
| **Title** | `title-md` | Inter | 1.125rem | 500 | Card Headings |
| **Body** | `body-md` | Inter | 0.875rem | 400 | Table Data / Descriptions |
| **Label** | `label-sm` | Inter | 0.6875rem | 600 | Uppercase Table Headers |

---

## 4. Elevation & Depth: Tonal Layering
Traditional shadows are too heavy for a data-centric module. We use **Ambient Shadows** and **Tonal Stacking**.

- **The Layering Principle:** Place a `surface-container-lowest` card on a `surface-container-low` background. This creates a natural "lift" without visual noise.
- **Ambient Shadows:** When a card must float, use `shadow-[0_8px_30px_rgb(0,0,0,0.04)]`. The shadow should be barely perceptible, mimicking soft, overhead studio lighting.
- **The "Ghost Border":** If accessibility requires a border, use `outline-variant` at 15% opacity (`border-outline-variant/15`). Never use 100% opaque borders.

---

## 5. Components

### Stat Cards (Financial Metrics)
- **Structure:** `surface-container-lowest` background, `xl` rounding.
- **Direction:** Place the `label-sm` above the `display-sm` figure.
- **Trend Indicators:** Use `primary_fixed` (Emerald) for income growth and `tertiary` (Red) for expense spikes. Use `material-symbols` (e.g., `trending_up`) with a `0.5rem` horizontal gap.

### Financial Data Tables
- **Sticky Headers:** Use `surface-container-high` with a 10% opacity blur.
- **Row Separation:** Forbid divider lines. Use a `1px` transparent border that switches to `surface-container-highest` on `:hover`.
- **Numbers:** Use tabular nums (font-variant-numeric: tabular-nums) to ensure decimals align perfectly.

### Status Badges (Semantic Indicators)
Badges should be pill-shaped (`rounded-full`) using low-saturation backgrounds to keep the focus on the data.
- **Approved:** Background: `primary_container` (Emerald tone) | Text: `on_primary_container`.
- **Pending:** Background: `secondary_container` (Grey/Blue tone) | Text: `on_secondary_container`.
- **Rejected:** Background: `error_container` (Red tone) | Text: `on_error_container`.

### Buttons
- **Primary:** `bg-primary` with `text-on-primary`. Use `rounded-lg`. 
- **Secondary:** No background. Use `border-outline-variant/20` and `text-primary`.
- **Interactions:** On hover, primary buttons should shift to `primary_container` with a subtle `shadow-lg`.

### Financial Charts (Chart.js Integration)
- **Line Charts:** Use `primary` for the stroke, with a gradient fill transitioning from `primary_container` (20% opacity) to `transparent` at the bottom.
- **Grid Lines:** Set grid line color to `outline-variant` at 5% opacity.

---

## 6. Do's and Don'ts

### Do
- **Do** use horizontal whitespace to separate table columns rather than vertical lines.
- **Do** use "Negative Space" as a functional element to group related financial figures.
- **Do** use `material-symbols` with a `weight: 300` for a lighter, more professional feel.
- **Do** ensure that in Dark Mode, the `surface` color remains slightly tinted with primary hues to avoid "pure black" fatigue.

### Don't
- **Don't** use pure black `#000000` for text. Use `on_surface` to maintain a high-end editorial feel.
- **Don't** use high-contrast borders for card containers. Use the Tonal Layering Principle.
- **Don't** use "Alert Red" for everything negative; use the `tertiary` tokens for a more sophisticated, muted financial warning.
- **Don't** overcrowd the sidebar; use `body-sm` for nav links and ensure ample vertical padding (`py-3`).