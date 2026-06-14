# Trail browser client

Published by `php artisan vendor:publish --tag=trail-js`. Re-run with `--force`
after upgrading the package.

```ts
import { createTrail } from '@/vendor/trail';

const client = createTrail();
client.track('chat.message_sent', { thread_id: 42 });
```

See the Trail wiki "Browser / SPA tracking" page for the adapter pattern, config
keys, the view vs write gate model, and the Amplitude migration note.
