# AI tooling setup (Claude Code, Codex, Cursor)

How instruction files are formatted for this plugin repo, based on official docs:

- [Claude Code memory](https://code.claude.com/docs/en/memory)
- [OpenAI Codex AGENTS.md](https://developers.openai.com/codex/guides/agents-md)

## File map

| Tool | Primary file | Also reads |
|------|----------------|------------|
| **OpenAI Codex** | `AGENTS.md` | Nested `AGENTS.md`, `AGENTS.override.md`; optional fallbacks via `config.toml` |
| **Claude Code** | `CLAUDE.md` | Imports `@AGENTS.md`; `.claude/rules/*.md`; `.claude/skills/` |
| **Cursor** | `.cursor/rules/*.mdc` | `.cursor/skills/site-migrator/SKILL.md` |

Codex does **not** load `CLAUDE.md` unless you add it to `project_doc_fallback_filenames`. Claude does **not** load `AGENTS.md` unless you `@AGENTS.md` in `CLAUDE.md` (configured here).

## OpenAI Codex

### Format

- Plain Markdown at repo root: **`AGENTS.md`**
- No required YAML frontmatter
- Keep combined project instructions under **32 KiB** (`project_doc_max_bytes` default) or they are **silently truncated**
- Put **exact commands** (copy-pasteable), not vague “run tests”
- Use a **Never** section for hard bans

### Optional global config

Copy snippets from [codex-config.example.toml](codex-config.example.toml) into `~/.codex/config.toml`:

- `project_doc_fallback_filenames = ["CLAUDE.md"]` — only if you drop Codex-only `CLAUDE.md` without `@AGENTS.md`
- Raise `project_doc_max_bytes` if `AGENTS.md` + nested files grow large

### Verify

```bash
codex --ask-for-approval never "Summarize the current instructions."
```

## Claude Code

### Format

- **`CLAUDE.md`** at repo root (or `.claude/CLAUDE.md`) — keep **under ~200 lines** per file
- **Import shared rules:** `@AGENTS.md` at top of `CLAUDE.md` (this repo does that)
- **Path-scoped rules:** `.claude/rules/*.md` with YAML frontmatter:

```yaml
---
paths:
  - "includes/**/*.php"
---
```

- **Skills:** `.claude/skills/<name>/SKILL.md` with frontmatter `name` + `description` (different from Cursor’s `.cursor/skills/` path)
- **Private notes:** `CLAUDE.local.md` (add to `.gitignore`)

### Generate starter

```text
/init
```

### Verify

Ask: “Which instruction files did you load?” — should mention `AGENTS.md` via import.

Official note: [Claude reads CLAUDE.md, not AGENTS.md alone](https://code.claude.com/docs/en/memory#agentsmd) — use `@AGENTS.md` or a symlink.

## Cursor

- Rules: `.cursor/rules/site-migrator.mdc` with `globs` in YAML frontmatter
- Skills: `.cursor/skills/site-migrator/SKILL.md`
- Cursor-specific; not read by Codex or Claude Code

## Monorepo vs plugin-only repo

| Git root | Put `AGENTS.md` / `CLAUDE.md` |
|----------|-------------------------------|
| Plugin only (`site-migrator/`) | This directory (current layout) |
| Whole WordPress tree (`wp/`) | `wp-content/plugins/site-migrator/` **and** consider a short pointer in repo root `AGENTS.md` linking here |

## What not to commit

- `CLAUDE.local.md`
- `AGENTS.override.md` with machine-specific paths (unless intentional)
- Customer URLs, auth codes, staging data
