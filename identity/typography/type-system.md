# Typography

One typeface keeps the system honest: **Inter**. Variable, multilingual, free.

## Stack

```css
font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', sans-serif;
```

The codebase already loads it from Google Fonts in `templates/layout_auth.php` and `templates/layout_authed.php`. Self-host from rsms.me/inter for offline-capable installs.

## Weights in use

| Weight | Role |
| --- | --- |
| 400 Regular | Body copy, report paragraphs |
| 500 Medium | UI labels, table cells, secondary buttons |
| 600 SemiBold | Subheadings, section titles, pill labels |
| 700 Bold | Display headings, wordmark, primary buttons |

Avoid weights below 400 (poor on shared-hosting render paths) and avoid 800/900 (too heavy beside the brand mark).

## Scale

| Token | Size / Line | Weight | Tracking | Color |
| --- | --- | --- | --- | --- |
| `display` | 56 / 64 | 700 | −0.025em | ink-900 |
| `h1` | 48 / 56 | 700 | −0.020em | ink-900 |
| `h2` | 32 / 40 | 700 | −0.020em | ink-900 |
| `h3` | 22 / 30 | 600 | −0.010em | ink-900 |
| `h4` | 16 / 24 | 600 | 0 | ink-900 |
| `body` | 14 / 22 | 400 | 0 | ink-700 |
| `body-sm` | 13 / 20 | 400 | 0 | ink-700 |
| `caption` | 12 / 18 | 400 | 0 | ink-600 |
| `eyebrow` | 11 / 14 | 700 | 0.18em UPPER | brand-700 |
| `mono-data` | 13 / 20 | 500 tabular | 0 | ink-800 |

`mono-data` is Inter with `font-feature-settings: 'tnum' 1, 'cv11' 1` — tabular figures for vitals, lab values, and dosages so columns stay aligned without switching faces.

## Wordmark

- Set as **`MedAgent·AI`** in Inter Bold.
- The separator is U+00B7 (middle dot), not a period — keeps optical balance.
- `MedAgent` in ink-900 (`#0F172A`). `·AI` in brand-700 (`#0E7490`).
- Default tracking: −0.020em. At sizes ≤ 18px set tracking to 0 to preserve legibility.
- Tagline (optional): `CLINICAL  INTELLIGENCE` — Inter SemiBold, 11px, uppercase, tracking 0.22em, two spaces between words, color ink-500.

## Localization notes

The app ships in EN / FR / DE. Inter covers all three plus extended Latin diacritics. For future RTL (Arabic/Hebrew) expansion, pair Inter with [IBM Plex Sans Arabic](https://www.ibm.com/plex/) — both share humanist proportions.

## Don't

- Don't substitute Roboto, Open Sans, or system-only stacks. The brand reads colder without Inter's tall x-height.
- Don't apply italics on body copy — reserve italic for Latin medical terms (e.g. *Staphylococcus aureus*).
- Don't letterspace body copy. Tracking adjustments are for display and eyebrow only.
