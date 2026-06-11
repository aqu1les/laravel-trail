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
