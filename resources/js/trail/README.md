# Trail browser client

Published by `php artisan vendor:publish --tag=trail-js`. Re-run with `--force`
after upgrading the package.

The tag copies these files to `resources/js/vendor/trail`. Import them from
there with whatever path your bundler resolves; `@/` below assumes the common
`@ -> resources/js` alias.

```ts
import { createTrail } from '@/vendor/trail'; // or '../vendor/trail' without the alias

const client = createTrail();
client.track('chat.message_sent', { thread_id: 42 });
```

See the Trail wiki "Browser / SPA tracking" page for the adapter pattern, config
keys, the view vs write gate model, and the Amplitude migration note.
