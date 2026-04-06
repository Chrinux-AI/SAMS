# Design System Specification: Editorial Collaboration

## 1. Overview & Creative North Star
The Creative North Star for this design system is **"The Sovereign Ledger."** 

In the context of school management accounting, users aren't just processing data; they are curating a legacy of institutional trust. This system moves away from the "busy SaaS dashboard" aesthetic toward a high-end editorial experience. We achieve this by treating financial data with the same gravitas as a premium broadsheet newspaper. 

The design breaks the "template" look through **Intentional Asymmetry** (e.g., sidebars that don't reach the bottom of the viewport) and **Tonal Depth** rather than structural lines. This is a signature, "no-line" environment where hierarchy is felt through surface elevation and typographic scale rather than boxes.

---

## 2. Colors & Surface Architecture

The palette is rooted in the authority of `primary` (#003d9b) and the clarity of `secondary` (#006c47). However, the sophistication of the system lies in the neutral tiers.

### The "No-Line" Rule
**Explicit Instruction:** Do not use 1px solid borders to define sections, table rows, or sidebar boundaries. You must define boundaries through background color shifts.
*   **Example:** A `surface-container-low` (#f4f3f8) sidebar sitting against a `surface` (#faf9fe) main content area.

### Surface Hierarchy & Nesting
Treat the UI as stacked sheets of premium paper. Use the following tiers to create depth:
- **`surface_container_lowest` (#ffffff):** The highest priority "active" layer (e.g., an open ledger entry).
- **`surface_container_low` (#f4f3f8):** The default background for secondary information.
- **`surface_dim` (#dad9de):** Used for background "voids" to make the primary workspace pop.

### The "Glass & Gradient" Rule
To elevate the experience from "utility" to "premium," use **Glassmorphism** for floating elements (like Toast notifications or quick-action menus). Apply `surface` colors at 80% opacity with a `20px` backdrop-blur. 
*   **Signature Textures:** For primary CTAs, use a subtle linear gradient from `primary` (#003d9b) to `primary_container` (#0052cc) at a 135-degree angle. This adds "soul" and prevents the flatness from feeling sterile.

---

## 3. Typography
We utilize a high-contrast scale to ensure financial data density remains legible and authoritative.

*   **Display (Large/Medium):** `inter`, 3.5rem - 2.75rem. Use exclusively for top-tier institutional metrics (e.g., "Annual Endowment Total"). 
*   **Headline (Small):** `inter`, 1.5rem. Used for section headers in the accounting suite. This should feel like an editorial sub-headline.
*   **Title (Medium):** `inter`, 1.125rem. This is your workhorse for card titles and ledger categories.
*   **Body (Medium):** `inter`, 0.875rem. Optimized for data density in tables.
*   **Label (Small):** `inter`, 0.6875rem. Use all-caps with +5% letter spacing for "Metadata" or "Status Labels" to provide an institutional feel.

---

## 4. Elevation & Depth

### The Layering Principle
Depth is achieved by "stacking" tones. Place a `surface-container-lowest` card on a `surface-container-low` section. The change in hex code provides enough visual friction to define the edge without a line.

### Ambient Shadows
When a "floating" effect is required (Modals or Popovers):
- **Blur:** 24px - 40px.
- **Opacity:** 4% - 6%.
- **Color:** Use a tinted version of `on_surface` (#1a1b1f) rather than pure black to keep the shadow feeling "ambient" and natural.

### The "Ghost Border" Fallback
If a border is required for accessibility (e.g., Input fields), use the `outline-variant` (#c3c6d6) at **15% opacity**. 100% opaque, high-contrast borders are strictly prohibited.

---

## 5. Components

### Buttons
- **Primary:** Gradient fill (`primary` to `primary_container`), `4px` radius. 
- **Secondary:** Transparent background with a "Ghost Border" and `primary` text.
- **Tertiary:** No background, `primary` text, underlined only on hover.

### Input Fields
- Use `surface_container_highest` (#e3e2e7) as the fill. 
- No border by default. On focus, transition the background to `surface_container_lowest` (#ffffff) with a `2px` `primary` bottom-only highlight.

### Cards & Lists
- **Forbid Divider Lines.** Use `16px` or `24px` of vertical white space to separate entries.
- For accounting ledgers, use alternating row backgrounds: `surface` and `surface_container_low`.

### Specialized School Components
- **The Ledger Row:** A custom component for transaction entries. On hover, the row should shift from `surface` to `surface_container_highest` with a `4px` left-accent of `surface_tint`.
- **Status Chips:** Use `secondary_container` for "Paid" and `error_container` for "Overdue." Text should always be the "on" variant (e.g., `on_secondary_container`) for maximum legibility.

---

## 6. Do's and Don'ts

### Do
- **Do** prioritize white space over lines. If the data feels cluttered, increase the padding, don't add a border.
- **Do** use `inter` Medium (500) for numerical data in tables to ensure "read-at-a-glance" clarity.
- **Do** use `9999px` (Full) roundness for Status Chips, but stick to `4px` for structural elements like Buttons and Cards.

### Don't
- **Don't** use standard "Drop Shadows." Use tonal layering first; if you must use a shadow, ensure it is extremely diffused and low-opacity.
- **Don't** use 100% black text. Always use `on_surface` (#1a1b1f) to maintain the editorial softness.
- **Don't** use icons as standalone actions without labels in the accounting suite. Trust is built through explicit clarity.