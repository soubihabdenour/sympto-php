# MedAgent AI — Brand Identity

A complete identity system for the MedAgent AI clinical decision-support tool.

> **Brand line:** Decision support, drafted in seconds.
> **Brand voice:** Calm, precise, deferential to the clinician. Never theatrical.

Open `brand-book.html` in a browser for the full visual guidelines. Everything below is the working reference.

---

## What's in this directory

```
identity/
├── README.md                    ← you are here
├── brand-book.html              ← open in browser: full visual brand book
│
├── logo/
│   ├── logo-mark.svg            ← square mark (80×80, gradient tile)
│   ├── logo-primary.svg         ← horizontal lockup: mark + wordmark + tagline
│   ├── logo-stacked.svg         ← stacked lockup (square contexts)
│   ├── logo-wordmark.svg        ← wordmark only
│   ├── logo-monochrome.svg      ← single-ink (ink-900 tile, white glyph)
│   ├── logo-reverse.svg         ← for dark backgrounds
│   ├── favicon.svg              ← 32×32 simplified glyph
│   └── app-icon.svg             ← 512×512 iOS/Android squircle
│
├── colors/
│   ├── palette.svg              ← visual swatches
│   ├── colors.json              ← W3C design-tokens format
│   └── colors.css               ← CSS custom properties (drop-in)
│
├── typography/
│   ├── typography.svg           ← type specimen
│   └── type-system.md           ← scale, weights, rules
│
├── mockups/
│   ├── business-card.svg        ← 85×55mm, front + back
│   ├── letterhead.svg           ← A4 portrait template
│   ├── social-avatar.svg        ← 400×400 square avatar
│   └── og-image.svg             ← 1200×630 Open Graph card
│
└── usage/
    ├── clear-space-and-sizing.svg
    └── dos-and-donts.svg
```

---

## The mark

The mark is a **pulse line ending in an AI sparkle** — a heartbeat that resolves into a four-point spark. Two readings in one glyph:

1. **Pulse line** → vital signs, the universal symbol of medical life.
2. **Sparkle** → AI augmentation, the system's value-add over a paper chart.

Set on a rounded-square tile (`rx = 20 / 80` ≈ 25% radius), filled with the brand gradient (`#0891B2 → #0E7490 → #155E75`).

### Construction

| Property | Value |
| --- | --- |
| Tile | 80 × 80, corner radius 20 |
| Glyph stroke | 3.5 (at 80px), round caps & joins |
| Glyph color | `#FFFFFF` |
| Sparkle cross-bars | stroke 2, opacity 0.7 |
| Min digital size | 32 px (use favicon variant below) |
| Min print size | 8 mm |

---

## Color

Primary: **`#0E7490` brand-700**. Always pair with ink (`#0F172A`) for body type and white for surfaces.

| Role | Token | Hex |
| --- | --- | --- |
| Primary brand | `brand-700` | `#0E7490` |
| Brand light (hover/pill) | `brand-50` | `#ECFEFF` |
| Brand dark (hover state) | `brand-800` | `#155E75` |
| Body text | `ink-900` | `#0F172A` |
| Muted text | `ink-500` | `#64748B` |
| Border | `ink-200` | `#E2E8F0` |
| Surface | white | `#FFFFFF` |
| Vital / success | `vital-500` | `#10B981` |
| Caution | `warn-500` | `#F59E0B` |
| Critical | `danger-500` | `#EF4444` |

Full token set: see `colors/colors.json` and `colors/colors.css`. The CSS file is a drop-in for the existing Tailwind config in `templates/layout_auth.php` — the brand and ink scales match exactly.

---

## Typography

**Inter** for everything. Variable weights 400 / 500 / 600 / 700.

```css
font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', sans-serif;
```

The wordmark sets as **`MedAgent·AI`** — middle-dot (U+00B7) separator, `MedAgent` in ink-900, `·AI` in brand-700. Optional eyebrow tagline: `CLINICAL  INTELLIGENCE` (Inter 600, 11px, tracking 0.22em, two spaces between words).

Full scale, line heights, and rules: see `typography/type-system.md`.

---

## Voice & boilerplate

| | |
| --- | --- |
| Name | MedAgent AI |
| Tagline (short) | Clinical Intelligence |
| Tagline (long) | Differential diagnosis, drafted in seconds. |
| Disclaimer | Decision support · Not a diagnostic device |
| Mandatory legal | "MedAgent AI is intended for licensed medical professionals. The treating doctor remains fully responsible for the final clinical decisions." |

The disclaimer is **non-optional** on any marketing surface where the product is named, including the OG card, business cards, letterhead footer, and the in-app landing page. It already lives at `templates/components/disclaimer.php`.

---

## Hooking the identity into the existing app

The current app already uses the right palette and font — `templates/layout_auth.php` defines a Tailwind theme that matches these tokens. To upgrade the existing logo:

1. **Favicon** — drop `identity/logo/favicon.svg` at `/favicon.svg` and add to `<head>`:
   ```html
   <link rel="icon" type="image/svg+xml" href="/favicon.svg">
   ```
2. **Brand mark in `sidebar.php` and `landing.php`** — replace the `icon('stethoscope')` call inside `.brand-logo` with the inline mark from `logo-mark.svg` (strip the `<rect>` tile; the `.brand-logo` class already provides the gradient tile).
3. **Open Graph card** — convert `mockups/og-image.svg` to PNG (1200×630) and reference it from the `<head>` of the landing page.
4. **App icon (PWA)** — convert `logo/app-icon.svg` to 192/512px PNG for `apple-touch-icon` and a future `manifest.json`.

No code changes are bundled here — this directory is a pure design deliverable. The handoff is intentional so engineering can apply the assets at the pace they choose.

---

## License

These brand assets belong to the MedAgent AI project. They are not licensed for use by third parties or to identify other products.
