# Site Migrator — product specification

**Owner:** Octarine Studio — https://octarinestudio.uk  
**Plugin URL:** https://octarinestudio.uk/wordpress-site-migrator  
**License:** GPL-3.0-or-later

## Problem

Developers and agencies need to clone a WordPress site (or multisite subsite) to another install without manual export/import, oversized zip files, or commercial migration SaaS for simple cases.

## Solution

A small WordPress plugin on **both** sites:

- **Source** shares site URL + secret auth code.
- **Target** pulls database, uploads, active theme, and active plugins in chunked steps, then applies with a single destructive confirm.

## Users

- Agency developers (staging → production or client → local)
- Site owners migrating to new hosting (technical admin)

## Core user stories

1. As a source admin, I copy my site URL and auth code so another install can pull from me.
2. As a target admin, I connect to a source, preview what will transfer, download to staging, then replace my site.
3. As a target admin, I can cancel, restart, or resume after a browser reload.
4. As a target admin, I want WordPress.org plugins installed from wordpress.org (not 10k+ files copied) when possible.
5. As a target admin, I want plugins already at the same version skipped.

## Out of scope (v1)

- Incremental/sync migrations
- Rollback after apply
- Multisite → multisite as primary flow
- Inactive themes/plugins (only active)
- mu-plugins directory
- Files over 25 MB per file
- Non–WordPress.org premium plugin licensing

## UX principles

- Native WordPress admin UI (postbox, form-table, notices, nav tabs).
- No emojis; minimal custom CSS.
- Clear warning before destructive apply.
- `beforeunload` while migration active.

## Success metrics

- Full site usable on target after apply (login, front page, admin).
- Plugin transfer time reduced vs raw file copy for WP.org plugins.
- No credentials or customer data in public repository.

## Compliance

- GPLv3-compatible distribution
- SECURITY.md and responsible disclosure via Octarine Studio
