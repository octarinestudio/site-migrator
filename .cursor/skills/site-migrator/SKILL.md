---
name: site-migrator
description: Site Migrator WordPress plugin (Cursor). For Claude Code use .claude/skills/site-migrator/. For Codex use AGENTS.md.
---

# Site Migrator development (Cursor)

## When to use

- Editing `wp-content/plugins/site-migrator/`
- Bugs in connect, download, apply, resume, cancel, or plugin transfer
- Adding REST endpoints or migration phases
- Security hardening or public-repo compliance

## Read first

1. `AGENTS.md` — architecture map  
2. `SECURITY.md` — threat model  
3. `docs/PRODUCT.md` — scope boundaries  

## Migration flow (target)

```
Connect (verify REST)
  → start_download (manifest + file list + filter plugins)
  → download_chunk loop (tables → files → phase done)
  → apply_chunk loop (staging tables → copy files → DB swap + URL fix → wp.org plugins → cleanup)
```

Session: `smig_session_{id}` (download), `smig_apply_{id}` (apply), option `smig_resume` (reload).

## Migration flow (source)

REST namespace `site-migrator/v1`: `verify`, `manifest`, `table-schema`, `table-rows`, `files-list`, `file-content`. Auth: header `X-Migrator-Auth` vs option `smig_auth_code`.

## Plugin transfer options

POST `wporg_install` / `skip_same_version` on `smig_start_download`. Logic in `SMIG_Plugin_Strategy::filter_file_queue()`. Apply installs via `install_from_wporg()` phase `install_wporg`.

## Debugging

1. Reproduce with source **site root URL** (not `/wp-json/...`).
2. Check source REST: `GET /wp-json/site-migrator/v1/verify` + auth header → 200 vs 403.
3. Inspect `wp-content/migrator-staging/manifest.json` and transients.
4. Enable `WP_DEBUG` for detailed AJAX errors only in dev.

## Public repo checks

- No secrets, staging, or customer URLs in git  
- `.gitignore` covers `migrator-staging/`  
- GPL headers and `LICENSE` present  

## Out of scope unless requested

Rollback, multisite→multisite, migrating inactive plugins, mu-plugins.
