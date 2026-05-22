---
paths:
  - "includes/**/*.php"
  - "site-migrator.php"
  - "uninstall.php"
---

# PHP rules

- Require `ABSPATH` guard in every PHP file.
- Use `SMIG_Security` for identifiers, source URLs, and HTTP `sslverify`.
- Chunk work in AJAX; refresh transients each chunk; update `smig_resume` when session changes.
- New REST routes: register in `class-source-api.php`, call via `SMIG_Admin::remote()` on target.
- DB errors in JSON: use `SMIG_Security::public_error_message()` unless `WP_DEBUG`.
