# MedAgent AI (PHP)

Plain-PHP port of the Next.js MedAgent AI clinical decision-support tool, sized
for Namecheap shared hosting: no Composer, no build step, just upload.

**This is not a patient-facing diagnosis tool.** Output is for licensed medical
professionals. The treating doctor remains fully responsible for the final
clinical decisions.

## Requirements

- PHP 8.2+ (Namecheap Stellar Plus and up)
- PHP extensions: `pdo_sqlite`, `mbstring`, `zip`, `json`, `curl`, `openssl`
- Apache + mod_rewrite (default on Namecheap cPanel)

## Local development

```bash
php -S 127.0.0.1:8000 -t .
# open http://127.0.0.1:8000
```

First request auto-creates `storage/db.sqlite` and seeds a demo doctor:
- email: `doctor@medagent.local`
- password: `medagent123`

## Configuration

Copy `.env.example` to `.env` and fill in. Required: `SESSION_SECRET`. To use a
real LLM, pick a provider via `LLM_PROVIDER` and set the matching key.

```
LLM_PROVIDER=anthropic   # openai | anthropic | gemini
ANTHROPIC_API_KEY=sk-ant-...
ANTHROPIC_MODEL=claude-opus-4-7
```

Without a configured provider the app runs in **demo mode**: the agent returns
clearly-labeled placeholder responses and never fabricates clinical content.

## Deploy to Namecheap

1. Zip the project (excluding `.git` and `storage/db.sqlite` if it exists locally).
2. In cPanel → File Manager, upload to `public_html/` and extract.
3. Make `storage/` and `storage/uploads/` writable (755 or 775).
4. Create `.env` in the root with at least `SESSION_SECRET` set to a long random
   string. Add LLM provider keys if you have them.
5. Browse to your domain. The DB and demo doctor are created on first request.

If your domain serves from a subdirectory, the `.htaccess` rewrite rules still
work — every URL is routed through `index.php`.

## What's different from the Next.js version

- **No PDF parsing** — Namecheap shared hosting can't easily run PDF libs.
  Upload DOCX or paste plain text instead.
- **No drag-and-drop** — file picker only.
- **No inline edit** of extracted text — re-upload the document.
- **No print/export view** — use your browser's print dialog on the report page.

Everything else — three LLM providers, EN/FR/DE UI, AI output in the doctor's
language, 15 specialty agents, document upload + extraction, structured
11-section report, audit log, demo mode — is identical.

## Layout

```
.
├── index.php             # front controller (Apache rewrites to here)
├── .htaccess             # mod_rewrite + sensitive-file blocks
├── .env                  # secrets (not committed)
├── routes.php            # URL → handler map
├── src/
│   ├── bootstrap.php     # env loader
│   ├── db.php            # PDO + schema bootstrap + demo seed
│   ├── router.php        # tiny request router
│   ├── auth.php          # sessions + password
│   ├── csrf.php          # CSRF token helpers
│   ├── audit.php
│   ├── i18n.php          # cookie-based locale, JSON message bundles
│   ├── specialties.php   # 15 specialist agent configs
│   ├── agents/
│   │   ├── prompts.php   # base prompt, locale-aware safety disclaimer
│   │   └── orchestrator.php  # generateReport / chatWithAgent
│   ├── llm/
│   │   ├── index.php     # provider router (llm_complete / llm_web_search)
│   │   ├── openai.php
│   │   ├── anthropic.php
│   │   └── gemini.php
│   ├── research/
│   │   └── search.php
│   └── documents/
│       └── parse.php
├── lang/{en,fr,de}.json
├── templates/
│   ├── layout_auth.php
│   ├── layout_authed.php
│   ├── components/
│   │   ├── sidebar.php
│   │   ├── disclaimer.php
│   │   ├── locale_switcher.php
│   │   ├── agent_card.php
│   │   └── report_viewer.php
│   └── (page templates)
└── storage/
    ├── db.sqlite         # auto-created
    └── uploads/          # per-case file storage
```
