# Project instructions

## Testing

Use `./vendor/bin/pest` to run tests, not `phpunit` or `bun test`.

```bash
./vendor/bin/pest
./vendor/bin/pest tests/Feature/SomeTest.php
```

## Documentation

Whenever you build or change a user-facing feature, behavior, or config option,
evaluate whether it needs to be reflected in the `README.md` or the GitHub wiki
(`wiki/`). If it does, update them as part of the same work. Internal-only
refactors that do not change documented behavior do not require doc updates.

## Writing style

- Never use the em dash (`—`) or en dash (`–`) anywhere in this project or its GitHub wiki.
  Use a regular hyphen (`-`) or rewrite the sentence. This applies to code comments, docblocks,
  Markdown docs (`README.md`, `SPEC.md`, `docs/`), Blade templates, and the wiki pages.

## Asking questions

Ask one question at a time. Wait for the answer before asking the next one.

This overrides any skill that batches questions into rounds (`/grilling` asks a whole
frontier at once, for example). Keep the skill's substance - the recommended answer,
the trade-offs, the reason the question exists - just deliver it one question per turn.
If several questions are ready at once, ask the one that unblocks the most and hold
the rest.

## Agent skills

### Issue tracker

Issues live as GitHub issues in `aqu1les/laravel-trail`, driven by the `gh` CLI.
See `docs/agents/issue-tracker.md`.

### Triage labels

The five canonical triage roles, each named exactly as the skills name them.
See `docs/agents/triage-labels.md`.

### Domain docs

Single-context: `CONTEXT.md` and `docs/adr/` at the repo root.
See `docs/agents/domain.md`.
