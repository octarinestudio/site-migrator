@AGENTS.md

## Claude Code

- Run `/init` only if this file is empty; prefer editing `AGENTS.md` and keeping this section short.
- Use plan mode before destructive apply-flow or DB swap changes.
- Path-scoped rules: `.claude/rules/`.
- Task workflow skill: `.claude/skills/site-migrator/` (invoke when debugging migration phases).
- Private overrides: `CLAUDE.local.md` (gitignored), not committed.
