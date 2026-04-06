# Design System Specification: The Academic Sentinel

## 1. Overview & Creative North Star
This design system is built to transform the administrative "weight" of school management into a high-end, editorial experience. We are moving away from the "SaaS-in-a-box" aesthetic—characterized by rigid grids and heavy borders—toward a philosophy we call **"The Digital Registrar."**

The Creative North Star is **Academic Authority.** The UI should feel like an elite university’s physical archive: structured, clean, and prestigious. We achieve this through "The Breathable Archive" layout—using intentional asymmetry, oversized display typography for context, and deep tonal layering that replaces traditional structural lines. By prioritizing negative space over borders, we create a calm environment for data-heavy attendance tasks.

---

## 2. Color & Tonal Architecture
The palette is rooted in a deep, authoritative Blue (`primary: #000666`) balanced against a sophisticated range of greys and off-whites. 

### The "No-Line" Rule
Standard 1px borders are strictly prohibited for layout sectioning. Separation of concerns must be achieved through **Background Color Shifts**. 
*   **Navigation:** Should sit on `surface_container_low`.
*   **Main Canvas:** Uses the `background` token (`#f8f9fa`).
*   **Actionable Cards:** Must use `surface_container_lowest` (#ffffff) to "pop" against the canvas.

### Surface Hierarchy & Nesting
Treat the UI as a series of stacked, premium paper sheets. 
*   **Base:** `background` (#f8f9fa).
*   **Sectioning:** Use `surface_container` for secondary modules.
*   **Focus Areas:** Use `surface_container_highest` for active widgets or highlighted data rows.

### Signature Textures
Main CTAs should never be flat. Use a subtle linear gradient from `primary` (#000666) to `primary_container` (#1a237e) at a 135-degree angle. This adds a "visual soul" and depth that feels custom-engineered.

---

## 3. Typography
We utilize a dual-sans-serif approach to balance modern editorial flair with high-density legibility.

*   **Display & Headlines (Manrope):** Chosen for its geometric precision and modern "tech" feel. Use `display-lg` for dashboard "Hero" stats (e.g., total attendance percentage) to create an unapologetic focal point.
*   **Body & Titles (Inter):** The workhorse for data. Inter’s tall x-height ensures that even `body-sm` (0.75rem) remains readable in dense student rosters.
*   **Editorial Hierarchy:** Always pair a `headline-sm` with a `label-md` in `on_surface_variant` (#454652) to provide context without cluttering the visual field.

---

## 4. Elevation & Depth
In this design system, shadows and blurs are tools of focus, not decoration.

### The Layering Principle
Rather than shadows, use **Tonal Stacking**. An attendance widget (Card) should be `surface_container_lowest` (#ffffff) placed on a `surface_container_low` (#f3f4f5) background. This creates a soft, "natural" lift.

### Ambient Shadows
For floating elements (Modals, Popovers), use a **Shadow-Tint**:
*   `Box-shadow: 0 20px 40px rgba(0, 7, 103, 0.06);` (Using a 6% opacity of `on_primary_fixed` to mimic natural light filtered through the primary brand color).

### The "Ghost Border" Fallback
If contrast ratios require a boundary, use a **Ghost Border**: `outline_variant` (#c6c5d4) at 20% opacity. 

### Glassmorphism
Topbars and Sidebars should employ a "Frosted Archive" effect:
*   `background: rgba(255, 255, 255, 0.8);`
*   `backdrop-filter: blur(12px);`
This allows the content scroll to bleed through subtly, maintaining a sense of place.

---

## 5. Components

### Sidebar Navigation
*   **Style:** Minimalist, using `surface_container_low`. 
*   **Active State:** No "pill" background. Instead, use a 4px vertical bar of `primary` on the far left and transition the text weight to Bold.
*   **Spacing:** Use `spacing.6` (1.3rem) between items to ensure an uncrowded, premium feel.

### Data-Heavy Tables
*   **Rule:** Forbid horizontal and vertical divider lines.
*   **Separation:** Use `spacing.4` vertical padding. Every second row uses `surface_container_low` for zebra-striping that feels like a subtle shift in light rather than a "grid."
*   **Typography:** Use `body-md` for row data and `label-sm` (Uppercase, 0.05em letter spacing) for headers.

### Dashboard Widgets (Role-Based)
*   **Visuals:** Use `roundedness.xl` (0.75rem) for all widget containers. 
*   **Hierarchy:** High-priority widgets (e.g., "Critical Absences") should feature a subtle `tertiary_container` (#5c1800) glow or top-border to signal urgency.

### Modal-Based CRUD Forms
*   **Overlay:** Use `inverse_surface` at 40% opacity with a `10px` blur.
*   **Form Logic:** Group related fields in "field-sets" defined by `surface_container` background blocks rather than borders.

### Buttons
*   **Primary:** Gradient (`primary` to `primary_container`), `roundedness.md`, white text.
*   **Secondary:** Ghost style. No background, `outline` token at 20% for the border, `primary` color for text.

---

## 6. Do's and Don'ts

### Do:
*   **Use Asymmetry:** Place a large `display-md` page title on the left and a cluster of primary actions on the right with significant white space (at least `spacing.16`) between them.
*   **Embrace "Empty" Space:** If a dashboard widget has no data, don't hide it. Show a clean, centered `label-md` illustration to maintain the layout's structural integrity.
*   **Layer Surfaces:** Always ask: "Can I use a color shift instead of a line?"

### Don't:
*   **Don't Use Pure Black:** Use `on_surface` (#191c1d) for text to maintain a high-end, "ink-on-paper" feel.
*   **Don't Over-Shadow:** If more than two elements have shadows, the interface will feel cluttered. Stick to Tonal Layering for 90% of the UI.
*   **Don't Cram:** If a table feels tight, increase the `spacing` scale rather than shrinking the font. Professionalism is measured in "breathing room."

---

## 7. Accessibility (WCAG 2.1 AA)
*   **Contrast:** All `on_surface` text against `surface` containers must exceed 4.5:1.
*   **Focus States:** Use a 2px `surface_tint` offset by 2px to ensure keyboard users have a high-visibility indicator that doesn't "break" the card's silhouette.
*   **Touch Targets:** Ensure all interactive chips and buttons maintain a minimum height of `spacing.10` (2.25rem).