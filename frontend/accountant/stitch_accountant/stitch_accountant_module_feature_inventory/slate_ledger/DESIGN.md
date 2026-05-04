# Design System Document: The Fiscal Architect

## 1. Overview & Creative North Star
The financial core of a school management system requires more than utility; it requires an atmosphere of **"The Fiscal Architect."** This North Star dictates a UI that feels structured, authoritative, and impossibly precise. 

To move beyond the generic "SaaS Dashboard" look, this design system rejects the standard 1px grid in favor of **Tonal Architecture**. We utilize intentional asymmetry—offsetting data visualizations against dense ledgers—to create a rhythmic flow. By layering surfaces of varying charcoal and slate tones, we create a digital environment that feels like a premium, physical workspace where high-stakes financial decisions are made.

## 2. Colors & Surface Philosophy
The palette is rooted in deep, light-absorbing charcoals, allowing the Atlassian-inspired blue to act as a beacon for action and status.

### The "No-Line" Rule
Traditional accounting software relies on heavy borders to separate data. This system prohibits 1px solid borders for sectioning. Structural boundaries must be defined through:
- **Background Shifts:** Using `surface_container_low` blocks against a `surface` background.
- **Tonal Transitions:** Defining the edge of a sidebar by moving from `surface_dim` to `surface_container`.

### Surface Hierarchy & Nesting
Think of the UI as a series of stacked, precision-cut sheets of fine slate.
- **Level 0 (Foundation):** `surface` (#0f1419) - The primary canvas.
- **Level 1 (Sub-sectioning):** `surface_container_low` (#171c21) - Used for side navigation or secondary panels.
- **Level 2 (Active Modules):** `surface_container` (#1b2025) - The main workspace area.
- **Level 3 (Interactive Cards):** `surface_container_high` (#252a30) - For ledger items or report cards that require focus.

### Signature Textures: The "Blue Soul"
To prevent a "flat" feeling, primary CTAs should not be a single hex code. Use a subtle linear gradient (135°) from `primary_container` (#579dff) to `primary` (#a8c8ff). This provides a professional polish and depth that mimics a backlit display.

## 3. Typography: Editorial Precision
We pair **Manrope** and **Inter** to balance aesthetic authority with data legibility.

- **Manrope (Display & Headlines):** Used for large-scale financial totals and section headers. Its geometric nature provides an "Editorial" feel.
  - *Display-LG (3.5rem):* For total revenue or end-of-year balances.
  - *Headline-SM (1.5rem):* For module titles (e.g., "Student Fee Ledger").
- **Inter (Body & Titles):** Used for all functional data. Inter is chosen for its high X-height and exceptional readability in dense accounting tables.
  - *Title-SM (1rem):* Bolded for column headers.
  - *Body-MD (0.875rem):* The workhorse for all ledger entries.
  - *Label-SM (0.6875rem):* For micro-metadata and audit timestamps.

## 4. Elevation & Depth
Depth is a functional tool, not a decoration. It signals priority without cluttering the accountant's field of vision.

- **Tonal Layering:** Instead of shadows, place a `surface_container_lowest` (#0a0f13) element inside a `surface_container_high` (#252a30) area to create an "inset" effect—perfect for search bars or input fields.
- **Ambient Shadows:** When a modal or pop-over is required, use a shadow with a 24px blur, 0px offset, and 6% opacity. The shadow color must be sampled from `on_surface` (#dee3ea) to create a natural, ambient light glow rather than a muddy black smear.
- **The "Ghost Border" Fallback:** If accessibility requires a border, use `outline_variant` (#414752) at **20% opacity**. It should be felt, not seen.
- **Glassmorphism:** For floating action buttons or utility bars, use `surface_container_highest` with a `backdrop-filter: blur(12px)` and 70% opacity. This "frosted slate" effect ensures the user never loses context of the data beneath.

## 5. Components

### The Lozenge (Status Badges)
Status is the heartbeat of accounting.
- **Shape:** `full` (9999px) roundedness.
- **Style:** Use a "Soft Fill" approach. A `tertiary_container` background with `on_tertiary_container` text. No borders.
- **Example:** "Overdue" uses `error_container` background with `on_error_container` text.

### High-Density Data Grids
- **The Rule:** No horizontal or vertical divider lines.
- **Separation:** Use a subtle background shift (`surface_container_low`) on hover.
- **Spacing:** Use 12px vertical padding (tight density) to maximize the amount of financial data visible on a single screen.

### Buttons
- **Primary:** Gradient fill (`primary_container` to `primary`). `lg` (0.5rem) roundedness.
- **Secondary:** Ghost style. No background, `outline_variant` at 20% opacity, text in `primary`.
- **Tertiary:** Text only, using `primary` for the label.

### Form Inputs
- **Visuals:** Background: `surface_container_lowest`. Border: Ghost Border (20% `outline_variant`).
- **States:** On focus, the border opacity jumps to 100% using `primary_container`.

### Ledger Cards
- To separate groups of fees, use a 24px vertical gap rather than a line. The change in white space acts as a cognitive "reset" for the accountant.

## 6. Do’s and Don'ts

### Do:
- **Use "Optical Alignment":** In tables, right-align currency and numbers to ensure decimals line up perfectly.
- **Embrace the Dark:** Use `on_surface_variant` (#c1c6d4) for secondary text to maintain a soft contrast ratio that reduces eye strain during long hours of auditing.
- **Layer with Purpose:** Only use the `surface_container_highest` for the most critical interactive elements.

### Don’t:
- **No Pure Black:** Never use `#000000`. It creates "smearing" on OLED screens and feels too harsh. Stick to the `surface` tokens.
- **No 100% White Text:** Pure white text on dark backgrounds causes "halation" (the text appears to glow and blur). Use `on_surface` (#dee3ea).
- **No Heavy Shadows:** If the shadow is the first thing you see, it’s too heavy. Depth should be implied by the color of the surfaces.