# Site Migrator

**Copyright (c) 2026 [Octarine Studio](https://octarinestudio.uk)**  
**License:** [GPL-3.0-or-later](LICENSE)  
**Plugin home:** https://octarinestudio.uk/wordpress-site-migrator

Pull-based WordPress migration: install on source and target, share a site URL + auth code, then pull database tables, media, theme, and plugins to the target.

## Requirements

- WordPress 6.0+
- PHP 7.4+
- `manage_options` on both sites

## Quick start

1. Install and activate on **both** sites.
2. **Source:** Tools → Site Migrator — copy **Site URL** and **Auth code**.
3. **Target:** enter those values → Connect → Download → Apply.

## Security

Read [SECURITY.md](SECURITY.md). The auth code grants full read access to the source site. Never commit staging data or secrets to this repository.

## Development

From the plugin directory:

```bash
composer install && composer check
npm install && npm run lint:js
```

Optional local WordPress (Docker): `npm run wp-env:start` — admin at http://localhost:8888/wp-admin (user `admin`, password `password`).

## Repository hygiene

This repo must **not** contain:

- Customer URLs, auth codes, or credentials
- `wp-content/migrator-staging/` or SQL dumps
- `.env` files

`.gitignore` is configured accordingly.

## AI-assisted development

See **[docs/AI-TOOLS.md](docs/AI-TOOLS.md)** for Claude Code vs Codex vs Cursor setup.

| File | Tool |
|------|------|
| [AGENTS.md](AGENTS.md) | **Codex** (primary); imported by Claude via `CLAUDE.md` |
| [CLAUDE.md](CLAUDE.md) | **Claude Code** (`@AGENTS.md` + short Claude-only notes) |
| [.claude/rules/](.claude/rules/) | Claude path-scoped rules |
| [.claude/skills/site-migrator/](.claude/skills/site-migrator/) | Claude Code skill |
| [.cursor/rules/](.cursor/rules/) | **Cursor** only |
| [docs/codex-config.example.toml](docs/codex-config.example.toml) | Optional `~/.codex/config.toml` snippets |

## License

This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
