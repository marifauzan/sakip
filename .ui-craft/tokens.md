# Design Tokens

<!-- Always-loaded by the ui-craft skill. Document the actual token values used in this project.
     Keep in sync with your CSS variables, Tailwind config, or design-tokens file. -->

## Colors

- **Primary Accent**: Emerald / Teal (`emerald-600` / `#059669` light, `#10b981` dark) — used strictly for primary CTAs, active tree nodes, key metrics, and focus rings. Accent budget: 3-5 placements per above-the-fold view.
- **Accent Light**: Emerald 50 (`#ecfdf5`), Emerald 100 (`#d1fae5`) — for active nav item background, subtle selection badges.
- **Neutral Ramp**: Slate / Zinc (`slate-50` to `slate-950`):
  - Canvas / Page background: `slate-50` (`#f8fafc`)
  - Card / Panel surface: `#ffffff` (light), bordered by `slate-200` (`#e2e8f0`)
  - Subdued background: `slate-100` (`#f1f5f9`)
  - Text Primary: `slate-900` (`#0f172a`)
  - Text Secondary: `slate-600` (`#475569`)
  - Text Muted / Labels: `slate-400` (`#94a3b8`)
- **Semantic Colors**:
  - Success / Approved: `emerald-600`
  - Warning / In-Progress: `amber-500`
  - Danger / Rejected / Errors: `rose-600`
  - AI Assistant Tone: `emerald-700` with subtle `emerald-50` containers (avoiding neon purple/indigo gradients)

## Typography

- **Font Family**: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif
- **Headings**: `font-semibold` / `font-bold`, with `-0.02em` tracking (`tracking-tight`) above 20px
- **Body**: 14px (`text-sm` / 0.875rem), `leading-relaxed` (1.5 - 1.6)
- **Captions / Eyebrows**: 11-12px (`text-xs`), uppercase with `tracking-wider` (0.05em)
- **Metrics / Numbers**: `tabular-nums` for all quantitative indicators, targets, baselines, and statistics

## Spacing

- Base 4px grid (Tailwind spacing scale):
  - Component internal padding: `px-3 py-1.5` (compact), `px-4 py-2.5` (standard)
  - Card interior padding: `p-5` to `p-6`
  - Stack gaps: `gap-3` (dense lists), `gap-6` (page sections)

## Radius

- **Inputs, Buttons, Dropdowns**: 6px (`rounded-md`)
- **Cards, Panels, Tables**: 10px / 12px (`rounded-lg` / `rounded-xl`)
- **Modals, Drawers, Canvas overlays**: 14px (`rounded-2xl`)
- **Pills / Badges**: 4px (`rounded`) or 9999px (`rounded-full`)

## Shadows & Elevation

- **Card flat**: `border border-slate-200/80 shadow-xs`
- **Dropdown / Flyout**: `shadow-md border border-slate-200`
- **Modal / Slide-over Drawer**: `shadow-xl border border-slate-200`
- Ambient + directional light (subtle, crisp, avoiding heavy colored glow drop-shadows)
