# Project instructions

## Testing

Use `./vendor/bin/pest` to run tests, not `phpunit` or `bun test`.

```bash
./vendor/bin/pest
./vendor/bin/pest tests/Feature/SomeTest.php
```

## Writing style

- Never use the em dash (`—`) or en dash (`–`) anywhere in this project or its GitHub wiki.
  Use a regular hyphen (`-`) or rewrite the sentence. This applies to code comments, docblocks,
  Markdown docs (`README.md`, `SPEC.md`, `docs/`), Blade templates, and the wiki pages.
