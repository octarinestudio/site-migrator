---
name: site-migrator
description: Site Migrator WordPress plugin — pull migration REST API, chunked download/apply, resume/cancel, WP.org plugin strategy. Use when editing this plugin, smig_ AJAX, migrator-staging, or Octarine Studio site migrator bugs.
---

# Site Migrator workflow

## Read first

1. [AGENTS.md](../../AGENTS.md)
2. [SECURITY.md](../../SECURITY.md)
3. [docs/PRODUCT.md](../../docs/PRODUCT.md)

## Target migration phases

```
smig_verify_source
  → smig_start_download (manifest, filter plugins)
  → smig_download_chunk (table_schema → table_rows → files → done)
  → smig_apply_chunk (create_staging → import_rows → copy_files → swap → install_wporg → cleanup)
```

Resume: option `smig_resume` + transients `smig_session_{id}` / `smig_apply_{id}`.

## Source REST

Namespace `site-migrator/v1`. Header `X-Migrator-Auth` must match option `smig_auth_code`.

## Plugin transfer

Options on `smig_start_download`: `wporg_install`, `skip_same_version`. Logic in `class-plugin-strategy.php`.

## Debug checklist

1. Source URL is site root, not REST path.
2. `curl` verify endpoint with auth header.
3. Inspect `wp-content/migrator-staging/manifest.json`.
4. Check transients and `smig_resume` option.

## Out of scope unless asked

Rollback, multisite→multisite, mu-plugins, inactive plugins.
