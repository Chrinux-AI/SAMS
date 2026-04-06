# Design System Documentation: Editorial Collaboration

## 1. Overview & Creative North Star
### Creative North Star: "The Structured Atelier"
This design system moves beyond the rigid, utilitarian nature of traditional productivity tools to create an "Atelier" for digital collaboration. Inspired by a high-end editorial aesthetic, it balances Atlassian’s structured logic with a premium sense of space, tonal depth, and intentional asymmetry.

The system rejects "boxy" layouts in favor of **Tonal Layering**. We treat the screen not as a flat canvas, but as an architectural space where hierarchy is defined by the weight of surfaces and the authority of typography. By breaking the standard grid with overlapping elements and shifting background values, we create an experience that feels custom-built rather than assembled from a template.

---

## 2. Colors
Our palette is anchored in a sophisticated "Atlassian Blue" (`primary`), supported by a high-contrast spectrum of neutral grays and vibrant secondary accents for utility.

### The "No-Line" Rule
**Explicit Instruction:** Traditional 1px solid borders for sectioning are strictly prohibited. Boundaries must be defined solely through background color shifts or subtle tonal transitions. For example, a sidebar using `surface_container_low` should sit adjacent to a main content area using `surface` without a divider line.

### Surface Hierarchy & Nesting
Treat the UI as a series of physical layers—like stacked sheets of fine paper. Importance is conveyed through the `surface-container` tiers:
*   **Base:** `surface` (#f9f9ff)
*   **De-emphasized/Background:** `surface_container_low` (#f0f3ff)
*   **Active/Elevated:** `surface_container_highest` (#d6e3ff)
*   **The Content Sheet:** `surface_container_lowest` (#ffffff) for primary cards or data inputs.

### The "Glass & Gradient" Rule
To inject "soul" into the professional interface:
*   **Glassmorphism:** Use semi-transparent surface tokens with `backdrop-blur: 20px` for floating navigation bars or modal overlays.
*   **Signature Gradients:** For primary CTAs or high-impact hero sections, use a subtle linear gradient transitioning from `primary` (#0050b2) to `primary_container` (#1868db) at a 135-degree angle.

---

## 3. Typography
The system utilizes a dual-font strategy to balance editorial authority with functional clarity.

*   **Display & Headlines (Manrope):** Chosen for its geometric precision and modern "tech-editorial" feel. Large scales (`display-lg` at 3.5rem) should be used with tight letter-spacing to create a bold, authoritative focal point.
*   **Body & UI (Inter):** Chosen for its exceptional legibility. Inter handles the "work" of the system, providing a neutral, accessible container for complex data and collaboration.

**The Hierarchy Logic:**
*   **Display/Headline:** Use `on_surface` (#091c35). These are your "Anchor Points."
*   **Body:** Use `on_surface_variant` (#424753) for long-form text to reduce eye strain and provide a softer, premium feel.
*   **Mono (System):** Reserved for technical metadata or collaborative "status" strings, providing a structured, utilitarian contrast to the organic Manrope headlines.

---

## 4. Elevation & Depth
Depth is a functional tool, not a stylistic flourish. We achieve it through **Tonal Layering** rather than structural lines.

*   **The Layering Principle:** Place a `surface_container_lowest` card on a `surface_container_low` section. The slight shift in brightness creates a soft, natural "lift" without the clutter of a border.
*   **Ambient Shadows:** If a "floating" element (like a dropdown or modal) is required, use extra-diffused shadows. 
    *   *Specification:* `box-shadow: 0 12px 40px rgba(9, 28, 53, 0.06);` (Using a tinted version of `on_surface`).
*   **The "Ghost Border" Fallback:** If accessibility requires a border, use the `outline_variant` token at **15% opacity**. Never use 100% opaque, high-contrast borders.
*   **Asymmetric Breathing Room:** To break the template look, use generous, intentional white space. Hero headlines should often be offset or "hanging" to create a sense of movement.

---

## 5. Components

### Buttons
*   **Primary:** Gradient fill (`primary` to `primary_container`), white text, `md` (0.375rem) roundedness.
*   **Secondary:** `surface_container_high` background with `primary` text. No border.
*   **Tertiary:** Ghost style. `on_surface` text with a `surface_variant` hover state.

### Input Fields
*   **Style:** Minimalist. Use `surface_container_low` as the background. No border.
*   **Focus State:** A 2px "Ghost Border" using `primary` at 40% opacity and a subtle `surface_tint` glow.
*   **Validation:** Errors use `error` (#ba1a1a) text but the container background should shift to `error_container` (#ffdad6) for high-scannability.

### Cards & Lists
*   **Constraint:** Forbid all divider lines.
*   **Separation:** Use vertical white space from the 8px spacing scale or alternate background tones (e.g., Zebra striping using `surface` and `surface_container_low`).

### Additional Signature Components
*   **The Contextual Hub:** A large-scale card using `surface_container_lowest` that overlaps two different background sections, creating an editorial "bridge" effect.
*   **Floating Status Chips:** Use `secondary_container` (#ffaa08) for high-visibility alerts, utilizing the `full` (9999px) roundedness for a friendly, organic feel.

---

## 6. Do's and Don'ts

### Do
*   **Do** use `surface_bright` to highlight the most important action area on a page.
*   **Do** utilize the `manrope` display scale for "Welcome" or "Success" states to inject brand personality.
*   **Do** favor asymmetric layouts—place content slightly off-center to create a modern, editorial vibe.
*   **Do** use `backdrop-blur` on navigation elements to maintain a sense of context and depth.

### Don'ts
*   **Don't** use 1px solid borders to separate sidebar, header, or content areas. Use background shifts.
*   **Don't** use pure black (#000) for text. Always use `on_surface` (#091c35) for better tonal harmony.
*   **Don't** crowd the interface. If a layout feels "busy," increase the vertical padding by one step in the spacing scale.
*   **Don't** use traditional "drop shadows" with high opacity. Shadows must feel like ambient light, not heavy ink.