# Security Policy

**Plugin:** Site Migrator  
**Developer:** [Octarine Studio](https://octarinestudio.uk)  
**Plugin URL:** https://octarinestudio.uk/wordpress-site-migrator  
**License:** GPL-3.0-or-later

## Supported versions

| Version | Supported |
| ------- | --------- |
| 1.0.x   | Yes       |

## Reporting a vulnerability

Please report security issues privately to Octarine Studio via https://octarinestudio.uk — do not open public GitHub issues for undisclosed vulnerabilities.

## Threat model

This plugin is a **full-site export/import tool**. Anyone who knows the source **auth code** can read the site's database and files via the REST API. Design assumptions:

1. Only trusted administrators run migrations.
2. The auth code is a shared secret (32 characters), rotated after use.
3. Source and target admins use HTTPS in production.

## Controls implemented

| Area | Mitigation |
|------|------------|
| REST API | `X-Migrator-Auth` header, `hash_equals()`, 403 on failure |
| Rate limiting | 120 requests/minute per IP on source REST routes |
| Admin AJAX | `manage_options`, `check_ajax_referer( 'smig_nonce' )` |
| SSRF (target) | `wp_http_validate_url()` on source URLs |
| TLS | `sslverify` **enabled by default** (`smig_http_sslverify` filter) |
| SQL identifiers | Table/column names restricted to `[a-zA-Z0-9_]+` |
| File paths | `..` stripped; source uses `realpath()` under allowed dirs |
| Staging | `wp-content/migrator-staging/` with `index.php` + `.htaccess` deny |
| Errors | DB errors hidden unless `WP_DEBUG` |
| Resume | Auth code **not** passed to JavaScript on reload (server session only) |
| Uninstall | Removes options, transients, and staging directory |

## Data stored locally

| Item | Location | Notes |
|------|----------|-------|
| Auth code | `wp_options.smig_auth_code` | Regenerate after migration |
| Pull endpoint | `wp_options.smig_endpoint_enabled` | Off by default; enable only while acting as a source |
| Resume state | `wp_options.smig_resume` | Includes source URL; auth in option for server resume only |
| Staging | `wp-content/migrator-staging/` | Full DB/files until apply or cancel; **not** committed to git |

## Operator checklist (public Git / production)

- [ ] Never commit `wp-content/migrator-staging/`, `.env`, or database dumps.
- [ ] Regenerate auth code after each migration.
- [ ] Deactivate plugin on source when migration is complete, or remove auth exposure.
- [ ] Use HTTPS on both sites.
- [ ] Restrict `manage_options` users.
- [ ] On nginx, deny web access to `/wp-content/migrator-staging/` (`.htaccess` only affects Apache).

## Known limitations

- **No encryption at rest** for staging files; filesystem access equals data access.
- **Premium/custom plugins** are copied from source, not WordPress.org.
- **Network-wide user tables** on multisite may export all network users.
- **Destructive apply** replaces target DB and files with no built-in rollback.

## GPL compliance

Source code is licensed under GPLv3 or later. See [LICENSE](LICENSE) and file headers. Third-party use must comply with the GPL.
