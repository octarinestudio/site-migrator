---
paths:
  - "assets/**/*"
---

# Admin UI rules

- Use WordPress core classes: `postbox`, `form-table`, `notice`, `button`, `spinner`, `nav-tab`.
- No emojis. Minimal custom CSS in `admin.css` only.
- Resume must not require auth code in JS; server session uses transients only.
- Use `beforeunload` while `migrationActive` during download/apply.
