# MedAgent AI — Progressive Web App

This document covers everything that was added on top of the existing PHP +
SQLite app to make it installable, offline-capable, and push-notification
ready. **No existing routes, templates, or DB tables were changed in a
breaking way.** The PHP architecture is intact.

---

## 1. What was added

```
manifest.webmanifest           PWA manifest (start_url, scope, icons, shortcuts)
sw.js                          Service worker (offline cache + push fallback)
offline.html                   Offline fallback page
browserconfig.xml              Windows tile metadata
icons/                         PNG icon set (192/512 + maskable + apple-splash)
  └── gen_pwa_icons.py         Re-generator (PIL); rerun if branding changes
screenshots/                   Install-dialog screenshots used by the manifest
assets/
  ├── pwa.css                  Install banner / iOS hint / toast / safe-area
  ├── pwa.js                   SW registration + install prompt + update banner
  └── notifications.js         OneSignal Web Push client (subscription sync)
src/push.php                   Server: subscription table + OneSignal REST
templates/
  ├── components/pwa_head.php  Drop-in <head> block used by both layouts
  └── admin_notifications.php  Admin broadcast composer + log
PWA.md                         This document
```

Modified files:
- `.htaccess` — HTTPS redirect, security headers, MIME types, SW no-cache, PWA caching.
- `index.php` — unchanged (front controller still dispatches `routes.php`).
- `routes.php` — appended `/api/push/{subscribe,unsubscribe,status}`,
  `/admin/notifications` (GET+POST), `/offline`.
- `src/bootstrap.php` — `require __DIR__ . '/push.php';` and `push_migrate();`.
- `templates/layout_auth.php`, `templates/layout_authed.php` — include
  `pwa_head.php`; added `viewport-fit=cover`.
- `templates/components/sidebar.php` — admin link to `/admin/notifications`.
- `templates/components/icons.php` — added `bell`, `alert-triangle`.
- `lang/{en,fr,de}.json` — added `Pwa.*`, `AdminNotif.*`, `Nav.broadcast`.
- `.env.example` — added `ONESIGNAL_APP_ID`, `ONESIGNAL_REST_API_KEY`, `APP_PUBLIC_URL`.

---

## 2. Installation

### 2.1 Local development

```bash
# Nothing to install — the PWA layer is plain PHP + JS + CSS.
# Start the existing built-in server:
php -S 127.0.0.1:8000 -t .
open http://127.0.0.1:8000
```

The service worker is automatically registered on every page in `assets/pwa.js`.
Chrome considers `http://127.0.0.1` and `http://localhost` secure contexts, so
SW registration + push prompts work locally without TLS.

### 2.2 Regenerating icons (only if branding changes)

The icon set is checked in. Re-run the generator only if you change the brand
palette or glyph:

```bash
python3 -m pip install --user pillow
python3 icons/gen_pwa_icons.py
```

### 2.3 OneSignal setup

1. Create an app at <https://app.onesignal.com> → choose **Web Push**.
2. In **Settings → Web Configuration**, pick **Typical Site** and use your
   production HTTPS origin (e.g. `https://medagent.example.com`).
3. **Subdomain integration must be OFF** — we host OneSignal's worker files
   on our own domain (see §3).
4. Download `OneSignalSDKWorker.js` from OneSignal's setup screen and place it
   at the project root next to `sw.js`. Browsers refuse to register service
   workers from a deeper path than where they live.
5. In **Settings → Keys & IDs**, copy the **App ID** and **REST API Key** into
   `.env` (alongside the existing LLM keys):

   ```dotenv
   ONESIGNAL_APP_ID="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"
   ONESIGNAL_REST_API_KEY="xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
   APP_PUBLIC_URL="https://medagent.example.com"
   ```

   The REST API key must never be sent to the browser. It's only read by
   `src/push.php` server-side.

When `ONESIGNAL_APP_ID` is unset, the OneSignal client script is **not loaded
at all** (see `templates/components/pwa_head.php`). The rest of the PWA — SW,
offline, install prompt — works regardless.

---

## 3. Deployment to Namecheap cPanel

### 3.1 File upload

Upload the entire project tree into `public_html/` (or a subdirectory, depending
on how your account is structured). Required files at the **document root**:

| Path | Purpose |
|---|---|
| `sw.js` | Service worker — must be at root for `scope: /`. |
| `manifest.webmanifest` | PWA manifest. |
| `offline.html` | Fallback page used when network is down. |
| `OneSignalSDKWorker.js` | Downloaded from OneSignal dashboard. Required for push. |
| `browserconfig.xml` | Windows tile metadata. |
| `icons/` | All icon PNGs. |
| `screenshots/` | Manifest screenshots. |
| `assets/` | `pwa.css`, `pwa.js`, `notifications.js`. |
| `.htaccess` | Updated rules. |
| `.env` | Server-side secrets — set permissions to **600**. |

```bash
# In cPanel "File Manager" or via SSH after upload:
cd ~/public_html
chmod 600 .env
chmod 750 storage
mkdir -p storage/uploads && chmod 750 storage/uploads
```

### 3.2 HTTPS (REQUIRED for PWA)

Service workers and Web Push only work over HTTPS (or `localhost`).
On Namecheap, install **AutoSSL** (or upload your own cert) via cPanel →
**SSL/TLS Status** → **Run AutoSSL**. The `.htaccess` already includes a
permanent HTTPS redirect:

```apacheconf
RewriteCond %{HTTPS} !=on
RewriteCond %{HTTP:X-Forwarded-Proto} !https
RewriteCond %{HTTP_HOST} !^(localhost|127\.0\.0\.1)
RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [R=301,L]
```

### 3.3 PHP version

Set the PHP version to **PHP 8.1+** in cPanel → **Select PHP Version** (the
code uses `str_starts_with`, `str_contains`, named arguments, and typed
properties). Extensions required:

- `pdo_sqlite` (or `pdo_mysql` if you migrate the DB layer later)
- `curl` (for OneSignal REST calls in `src/push.php`)
- `mbstring`
- `openssl`

### 3.4 First request

Visit `https://your-domain.tld/`. On the first request:

1. `src/bootstrap.php` runs `db_init()` (existing) and `push_migrate()`
   (new), which creates `push_subscriptions` and `push_broadcasts` tables.
2. The page registers `/sw.js`. You can confirm in DevTools → **Application →
   Service Workers**.
3. The page links to `/manifest.webmanifest`. DevTools → **Application →
   Manifest** shows the parsed icons, theme color, shortcuts, etc.

### 3.5 cPanel-specific gotchas

- **mod_rewrite must be enabled.** It usually is on Namecheap shared hosting.
  The existing routing already relied on it.
- **Hot-link protection / Mod_security WAF** sometimes rewrites POST bodies.
  If `/api/push/subscribe` returns 419 or 403 unexpectedly, whitelist `/api/*`
  in cPanel → **Security → ModSecurity**.
- **PHP error log:** `~/public_html/error_log` — check here if SW or push
  endpoints return 500.

---

## 4. PWA architecture cheat-sheet

### 4.1 Service worker caching strategy (`sw.js`)

| Request | Strategy | Notes |
|---|---|---|
| `GET` HTML navigation | **Network-first** → cache → `/offline.html` | Navigation Preload enabled. |
| `GET` `/api/**`, `/admin/**`, `/login`, `/logout`, `/cases/*/{messages,report,documents}` | **Bypass cache** | Always live; protects PHI. |
| `GET` static (`/icons/**`, `/assets/**`, `*.css`, `*.js`, `*.png`, fonts) | **Stale-while-revalidate** | Fast offline + fresh in background. |
| `GET` cross-origin (Google Fonts, Tailwind CDN) | **Cache-first** | Long-lived, opaque-tolerated. |
| `POST` / other writes | **Never intercepted** | Browser handles directly. |

Bump `APP_VERSION` in `sw.js` whenever precached files change. On the next
load, the new SW installs in the background; `assets/pwa.js` surfaces a
"Reload to update" pill that calls `skipWaiting()` + reloads.

### 4.2 Security guarantees

- Service worker is served with `Cache-Control: no-cache, no-store,
  must-revalidate` (see `.htaccess`) so updates roll out within one page load.
- The SW **never caches** any response containing `Set-Cookie` or
  `Cache-Control: private` / `no-store` (see `isCacheableResponse`).
- The SW **never intercepts** `POST` requests at all.
- The SW **never intercepts** `/api/`, `/admin/`, `/login`, `/logout`,
  `/cases/*/messages`, `/cases/*/report`, `/cases/*/documents` (PHI- or
  CSRF-sensitive paths).
- Push subscribe/unsubscribe endpoints require CSRF (`csrf_check()`) and a
  logged-in doctor (`require_doctor_json()`).
- The OneSignal **REST API key is server-side only** (`src/push.php`). The
  browser only ever sees the public App ID.
- Notifications include a documented PHI policy in the admin UI
  (`AdminNotif.privacyNote`).

### 4.3 Database additions

```sql
CREATE TABLE push_subscriptions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    doctor_id INTEGER NOT NULL REFERENCES doctors(id) ON DELETE CASCADE,
    player_id TEXT NOT NULL,
    platform TEXT,
    user_agent TEXT,
    ip TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    last_seen_at TEXT NOT NULL DEFAULT (datetime('now')),
    UNIQUE(doctor_id, player_id)
);
CREATE TABLE push_broadcasts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    admin_id INTEGER REFERENCES doctors(id) ON DELETE SET NULL,
    title TEXT NOT NULL,
    body TEXT NOT NULL,
    url TEXT,
    audience TEXT NOT NULL DEFAULT 'all',
    recipients INTEGER NOT NULL DEFAULT 0,
    onesignal_id TEXT,
    error TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
```

Both are created idempotently by `push_migrate()` in `src/push.php`. The
schema is compatible with the existing SQLite store; for a MySQL migration
later, swap `AUTOINCREMENT` → `AUTO_INCREMENT` and `datetime('now')` →
`CURRENT_TIMESTAMP`.

### 4.4 Push endpoints

| Method | Path | Auth | Body | Behavior |
|---|---|---|---|---|
| `POST` | `/api/push/subscribe`   | Doctor + CSRF | `{ player_id, platform? }` | Upserts `push_subscriptions`. |
| `POST` | `/api/push/unsubscribe` | Doctor + CSRF | `{ player_id }` | Deletes row. |
| `GET`  | `/api/push/status`      | Doctor (JSON) | — | Reports config + device count. |
| `GET`  | `/admin/notifications`  | Admin | — | Composer + history. |
| `POST` | `/admin/notifications`  | Admin + CSRF | `title, body, url?, audience, doctor_id?` | Sends via OneSignal, logs broadcast. |

---

## 5. Testing checklist

Run through this list before announcing the PWA to clinicians.

### 5.1 Install / manifest

- [ ] DevTools → **Application → Manifest** shows no warnings.
- [ ] All 10 icons resolve (200, correct MIME).
- [ ] Maskable icons display correctly in <https://maskable.app/editor>.
- [ ] On Android Chrome: "Add to Home screen" appears in the menu and produces a standalone window.
- [ ] On desktop Chrome/Edge: install button (⊕) appears in the omnibox.
- [ ] On iOS Safari 16.4+: Share → Add to Home Screen → tap the new icon → app opens fullscreen with `MedAgent AI` title (no Safari chrome).
- [ ] Splash screen shows brand teal on iPhone with notch.
- [ ] PWA shortcuts (long-press the icon on Android) show "New case" and "Dashboard".

### 5.2 Service worker / offline

- [ ] DevTools → **Application → Service Workers** shows `/sw.js` `activated and running`, scope `/`.
- [ ] DevTools → **Application → Cache Storage** lists `medagent-precache-v1.0.0`, `medagent-runtime-v1.0.0`, `medagent-fonts-v1.0.0`.
- [ ] Toggle DevTools → Network → **Offline**, then navigate to a page you've previously visited → it loads from cache.
- [ ] Navigate to a never-visited page while offline → `/offline.html` shows.
- [ ] Try a `POST` while offline → it fails with a network error (it is NOT served from cache).
- [ ] `/login`, `/api/...`, `/admin/...` are NEVER served from cache (check DevTools Network "from ServiceWorker" column).
- [ ] Bump `APP_VERSION` in `sw.js`, reload → "New version available · Reload" pill appears at the bottom → reload completes within ~1s.

### 5.3 Push notifications (when OneSignal is configured)

- [ ] Visit two pages (the slidedown waits for `pageViews: 2, timeDelay: 20s`).
- [ ] Allow notifications → DevTools → Application → Service Workers shows two SWs: `/sw.js` and `OneSignalSDKWorker.js`.
- [ ] Check the DB: `SELECT * FROM push_subscriptions;` should now have a row.
- [ ] Admin → Notifications → fill title + body, audience: All → Send → OneSignal dashboard shows the message → device receives it within ~5s.
- [ ] Click the notification → app opens to `/dashboard` (or the URL you specified).
- [ ] Toggle off push in browser settings → `/api/push/unsubscribe` is called → DB row removed.
- [ ] Logout → push remains valid; on re-login no duplicate row is created.

### 5.3a Medication reminders

- [ ] Open a case → **Medication reminders** card is visible.
- [ ] Add a one-shot reminder for ~2 minutes in the future → wait for cron tick → push arrives on the subscribed device → tap → opens `/cases/{id}#reminders`.
- [ ] Add a recurring reminder (every 6h) → after firing, the next_due_at advances by 6h in the UI.
- [ ] Snooze a reminder by 15 min → the listed next time shifts.
- [ ] Pause a reminder → it does NOT fire on the next cron tick.
- [ ] Stop the cron for 2 hours, restart → previously-due reminders fire only ONCE each (catch-up logic), not for every missed slot.
- [ ] Hit `/api/cron/reminders` without `?token=` → 403. With wrong token → 403. With correct token → 200 JSON `{ok:true, ...}`.

### 5.4 Security

- [ ] `https://your-domain.tld/.env` returns 403 (existing `.htaccess` rule).
- [ ] `https://your-domain.tld/src/push.php` returns 403.
- [ ] `https://your-domain.tld/api/push/subscribe` without CSRF returns 419.
- [ ] `https://your-domain.tld/admin/notifications` while logged out returns 302 → `/login`.
- [ ] HTTPS redirect: `http://your-domain.tld/dashboard` → `https://...`.
- [ ] HTTP response headers include `Strict-Transport-Security`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`.

### 5.5 Mobile responsiveness

- [ ] Resize Chrome to 320 × 568 (iPhone SE small) → no horizontal scroll on any page.
- [ ] All buttons / nav items meet the 44 × 44 pt tap target (existing `min-h-[40px]` and `min-h-[44px]` classes).
- [ ] iPhone with notch: top app bar respects `env(safe-area-inset-top)`.
- [ ] Inputs do not zoom on focus (font-size: 16 px in `pwa.css`).
- [ ] Drawer (`templates/layout_authed.php`) opens/closes smoothly.

### 5.6 Lighthouse (Chrome DevTools → Lighthouse → Mobile / PWA)

Target score: **100 / 100 PWA**, ≥ 90 on Performance + Accessibility +
Best Practices.

Expected pass/fail signals:

| Audit | Expected |
|---|---|
| Web app manifest meets the installability requirements | ✅ |
| Registers a service worker that controls page and start_url | ✅ |
| Provides a valid `apple-touch-icon` | ✅ |
| Sets a theme color for the address bar | ✅ |
| Content sized correctly for the viewport | ✅ |
| Uses HTTPS | ✅ (production) / ⚠ (localhost — acceptable) |
| Redirects HTTP traffic to HTTPS | ✅ (production) |
| Has a `<meta name="viewport">` tag with `width` or `initial-scale` | ✅ |
| Page has the HTML doctype | ✅ |
| Manifest doesn't have a maskable icon | ✅ — both maskable icons present |
| Provides a valid `apple-mobile-web-app-capable` value | ✅ |

If Lighthouse complains about `start_url` returning a status other than 200,
verify your HTTPS cert is valid and that you ran the audit on the deployed
domain — `https://localhost` certs cause false negatives.

### 5.7 Lighthouse run

```bash
# CLI alternative — requires Node ≥ 18:
npx lighthouse https://medagent.example.com \
  --preset=desktop --output=html --output-path=./lighthouse-desktop.html
npx lighthouse https://medagent.example.com \
  --form-factor=mobile --throttling-method=devtools \
  --output=html --output-path=./lighthouse-mobile.html
```

Open the resulting HTML and confirm the PWA section is green across the
board. Common findings to fix before launch:
- *"Tap targets are not sized appropriately"* — bump min-height on the
  affected element to `44px`.
- *"Image elements do not have explicit width and height"* — add
  `width`/`height` attributes to any new `<img>` you introduce.
- *"Document does not have a meta description"* — `templates/layout_auth.php`
  already sets OG tags; you may want a `<meta name="description">` too.

---

## 6. How users install

### Android (Chrome / Edge / Brave)
1. Open the site in the browser.
2. After 2 page views (≈ 20 s), the install banner slides up.
3. Tap **Install** → the app appears on the home screen.
4. (Alternative) Tap browser menu → **Add to Home screen**.

### iPhone / iPad (Safari, iOS 16.4+)
1. Open the site in Safari (not in a third-party browser).
2. The hint banner explains: tap the **Share** icon → **Add to Home Screen**.
3. Push notifications work only when launched from the home-screen icon
   (Apple requirement).

### Desktop (Chrome / Edge)
1. Click the install icon (⊕) in the omnibox, or use the install banner.
2. The app opens in its own window with no browser chrome.

---

## 6.5 Medication reminders (push reminders to give a patient medicine)

Doctors can attach time-scheduled reminders to any case (`/cases/{id}` →
**Medication reminders** card). At the due time, a push notification is
delivered to all of the owning doctor's installed devices via OneSignal.

### What gets stored / sent

| Field | Stored in DB? | Sent in notification? |
|---|---|---|
| Medication name (e.g. "Amoxicillin") | ✓ | ✓ in **title** |
| Dosage / route (e.g. "500 mg PO") | ✓ | ✓ in **body** |
| Doctor-set patient label (e.g. "Bed 12", "Mr. S") | ✓ | ✓ in **body** |
| Notes (≤ 80 chars) | ✓ | ✓ in **body** |
| Notes (> 80 chars) | ✓ | ✗ (omitted from notification) |
| Patient demographics, age, vitals, etc. | unchanged | **NEVER** sent |
| Document contents | unchanged | **NEVER** sent |

The UI explicitly warns to keep patient labels PHI-free.

### Setup — cPanel cron

This is the **only** thing you must wire up on the host after deploying. The
schema and routes ship in the code; the cron simply pokes a URL every minute.

1. Generate a secret and put it in `.env`:

   ```bash
   php -r "echo bin2hex(random_bytes(32)), \"\\n\";"
   # paste into .env:
   CRON_SECRET="d1c4f6b3...your-long-random-string"
   ```

2. cPanel → **Cron Jobs** → add a new job:

   | Field | Value |
   |---|---|
   | Common Settings | "Once per minute (`* * * * *`)" |
   | Command | `curl -fsS "https://your-domain.tld/api/cron/reminders?token=YOUR_CRON_SECRET" > /dev/null` |

   (Or use the `X-Cron-Token` header instead of the query string if your shared
   host masks query strings in logs:
   `curl -fsS -H "X-Cron-Token: YOUR_CRON_SECRET" https://your-domain.tld/api/cron/reminders > /dev/null`.)

3. Verify it ran by tailing the audit log:

   ```sql
   SELECT created_at, action, detail FROM audit_logs
   WHERE action LIKE 'reminder.%' ORDER BY id DESC LIMIT 20;
   ```

   You should see one `reminder.fire` row per dispatched reminder.

### Endpoint reference

| Method | Path | Auth | Effect |
|---|---|---|---|
| `POST` | `/cases/{id}/reminders` | Doctor + CSRF | Create reminder. Form fields: `medication, dosage, notes, patient_label, start_at` (datetime-local), `tz` (IANA, browser-supplied), `repeat_interval_minutes` (0, 240, 360, 480, 720, 1440), `repeat_until`. |
| `POST` | `/cases/{id}/reminders/{rid}/status` | Doctor + CSRF | Pause / resume / mark done. `status` field. |
| `POST` | `/cases/{id}/reminders/{rid}/snooze` | Doctor + CSRF | Push next firing forward by `minutes` (5–1440). |
| `POST` | `/cases/{id}/reminders/{rid}/delete` | Doctor + CSRF | Delete. |
| `GET`/`POST` | `/api/cron/reminders` | Shared secret (`?token=` or `X-Cron-Token`) | Dispatches all due reminders. Returns JSON summary `{considered, sent, failed, skipped, errors[], ran_at}`. |

### Catch-up semantics

If the cron is offline for a while (host downtime, deploy, daylight saving),
the worker does **not** fire the reminder once per missed slot. Instead, when
the next tick arrives, each missed reminder is dispatched **once** and
`next_due_at` is fast-forwarded to the next future slot in the schedule.
This avoids notification storms after maintenance windows.

### Idempotency / safety

- The dispatcher always advances `next_due_at` (or marks `done`) inside the
  same DB write that records the send — so a transient OneSignal failure
  records `last_error` on the row but does not loop firing the same minute.
- Per-tick batch size is capped (`REMINDER_BATCH = 100`, query string
  `?limit=` to override up to 1000).
- Per-case reminder cap (`REMINDER_MAX_PER_CASE = 50`) enforced server-side.
- Max interval is 1 week to prevent obvious mistakes.
- Cron endpoint is **constant-time compared** (`hash_equals`), returns 503
  if `CRON_SECRET` is unset (fail-safe — better to not run than to expose).

### Schema

```sql
CREATE TABLE medication_reminders (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    case_id INTEGER NOT NULL REFERENCES cases(id) ON DELETE CASCADE,
    doctor_id INTEGER NOT NULL REFERENCES doctors(id) ON DELETE CASCADE,
    medication TEXT NOT NULL,
    dosage TEXT,
    notes TEXT,
    patient_label TEXT,
    next_due_at TEXT NOT NULL,                -- UTC, advances after each fire
    repeat_interval_minutes INTEGER NOT NULL DEFAULT 0,  -- 0 = one-shot
    repeat_until TEXT,                         -- UTC, NULL = forever
    status TEXT NOT NULL DEFAULT 'active',    -- active | paused | done
    last_sent_at TEXT,
    last_error TEXT,
    sent_count INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX idx_reminders_due  ON medication_reminders(status, next_due_at);
CREATE INDEX idx_reminders_case ON medication_reminders(case_id);
```

Created idempotently by `reminders_migrate()` on every request.

---

## 7. Operations & monitoring

- **Subscriber count** is shown on `/admin/notifications`.
- **Broadcast log** (audience, recipients, status) is persisted in
  `push_broadcasts` and listed on the same page (most recent 25).
- **Audit trail**: subscribe/unsubscribe/broadcast events also land in the
  existing `audit_logs` table (`push.subscribe`, `push.unsubscribe`,
  `push.broadcast`).
- OneSignal dashboard provides delivery analytics (sent / delivered / clicked)
  at <https://app.onesignal.com>.

To clear all caches for every user (e.g. after a major redesign), bump
`APP_VERSION` in `sw.js`. Old caches are deleted by the SW's `activate`
handler on the next visit.

---

## 8. Troubleshooting

| Symptom | Likely cause | Fix |
|---|---|---|
| "DOMException: failed to register a ServiceWorker" | Page served over HTTP. | Enable HTTPS / AutoSSL. |
| Install banner never appears | Dismissed within last 14 days, OR already installed, OR criteria not met. | Clear `localStorage` key `pwa.install.dismissed.until`. |
| iOS does not save the home-screen icon | iOS < 16.4 OR opened in Chrome iOS. | Use Safari, iOS 16.4 or newer. |
| Push prompt never appears | `ONESIGNAL_APP_ID` missing, OR slidedown delay not yet met (2 pageviews + 20 s). | Set env var, browse around. |
| `POST /api/push/subscribe` returns 419 | CSRF mismatch (cached old page). | Reload the page so the meta tag is fresh. |
| Notifications send "0 recipients" | No installs have opted in yet. | Open the site on a device, accept the prompt, retry. |
| OneSignal worker 404 | `OneSignalSDKWorker.js` not uploaded to web root. | Download from OneSignal dashboard, place next to `sw.js`. |
| Service worker keeps the old version | Browser cached `sw.js`. | `.htaccess` sets `no-cache` — hard-reload (Cmd-Shift-R) once. |
