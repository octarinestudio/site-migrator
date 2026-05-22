# Site Migrator

**Product:** https://octarinestudio.uk/wordpress-site-migrator  
**Copyright:** Octarine Studio — https://octarinestudio.uk  
**License:** GPL-3.0-or-later

WordPress plugin: pull-based migration (source REST + target admin wizard). Install on both sites.

## Repository expectations

- GPL-3.0-or-later on new PHP files; Octarine Studio copyright in headers.
- Text domain: `site-migrator`; escape all admin output.
- Native WordPress admin UI only (postbox, form-table, notice, spinner).
- Prove bugs with logs/repro before changing behaviour; see [SECURITY.md](SECURITY.md).

## Commands

Run from this directory (plugin root):

```bash
php -l site-migrator.php
php -l includes/class-admin.php
php -l includes/class-source-api.php
php -l includes/class-security.php
php -l includes/class-plugin-strategy.php
```

Manual test path: **Tools → Site Migrator** in wp-admin (`manage_options` required).

Verify source REST (replace URL and auth):

```bash
curl -sS -H "X-Migrator-Auth: YOUR_AUTH_CODE" "https://SOURCE-SITE.example/wp-json/site-migrator/v1/verify"
```

## Architecture

| File | Role |
|------|------|
| `site-migrator.php` | Bootstrap, hooks |
| `includes/class-source-api.php` | Source REST (`verify`, `manifest`, tables, files) |
| `includes/class-admin.php` | Target UI + AJAX (download, apply, resume, cancel) |
| `includes/class-plugin-strategy.php` | WP.org install / skip same-version |
| `includes/class-security.php` | URL validation, identifiers, rate limit, TLS |
| `assets/admin.js`, `assets/admin.css` | Wizard UI |

**Staging (never commit):** `wp-content/migrator-staging/`  
**State:** transients `smig_session_*`, `smig_apply_*`; option `smig_resume`

**Target flow:** Connect → Download → Apply (full replace, no rollback).  
**Source URL on target:** site root only (e.g. `https://example.com`), not `/wp-json/...`.

## Security (non-negotiable)

- Auth code grants full read access to source; never log it or pass to `wp_localize_script`.
- Use `SMIG_Security::sslverify()` (default true); do not disable without `smig_http_sslverify` filter.
- Table/column names: `SMIG_Security::is_valid_identifier()` only.
- Remote URLs: `SMIG_Security::validate_source_url()`.
- AJAX: `manage_options` + `check_ajax_referer( 'smig_nonce' )`.

## Never

- Commit `migrator-staging/`, SQL dumps, `.env`, or auth codes.
- Set `sslverify` to false by default.
- Expose `source_auth` in browser-visible JSON on resume.
- Add customer-specific URLs in examples (use `https://example.com`).
- Ship changes without updating [SECURITY.md](SECURITY.md) when threat model changes.

## Apply phase order

`create_staging` → `import_rows` → `swap` → `post_swap` → `copy_files` → `install_wporg` → `cleanup`

## Docs

- [README.md](README.md) — install  
- [SECURITY.md](SECURITY.md) — threat model  
- [docs/PRODUCT.md](docs/PRODUCT.md) — product scope  
- [docs/AI-TOOLS.md](docs/AI-TOOLS.md) — Claude Code, Codex, Cursor setup  
