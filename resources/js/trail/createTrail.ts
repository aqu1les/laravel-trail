import type { TrailClient, TrailEventInput, TrailOptions, TrailProperties } from './types';

const SESSION_KEY = 'trail_session_id';

function readCookie(name: string): string | null {
  if (typeof document === 'undefined') return null;
  const match = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'));
  return match ? decodeURIComponent(match[1]) : null;
}

function resolveSessionId(): string {
  try {
    const existing = localStorage.getItem(SESSION_KEY);
    if (existing) return existing;
    const id = 'anon-' + Math.random().toString(36).slice(2) + Date.now().toString(36);
    localStorage.setItem(SESSION_KEY, id);
    return id;
  } catch {
    // localStorage unavailable (private mode / SSR) - fall back to ephemeral id.
    return 'anon-' + Math.random().toString(36).slice(2);
  }
}

export function createTrail(options: TrailOptions = {}): TrailClient {
  const endpoint = options.endpoint ?? '/trail/api/ingest';
  const flushAt = options.flushAt ?? 20;
  const flushInterval = options.flushInterval ?? 5000;
  const maxBufferSize = options.maxBufferSize ?? 1000;
  const pageViewEvent = options.pageViewEvent ?? 'page.viewed';

  let buffer: TrailEventInput[] = [];
  const sessionId = resolveSessionId();

  let timer: ReturnType<typeof setInterval> | null = null;
  if (flushInterval > 0 && typeof setInterval !== 'undefined') {
    timer = setInterval(() => void flush(), flushInterval);
  }

  function enqueue(name: string, properties?: TrailProperties, value?: number): void {
    const event: TrailEventInput = {
      name,
      occurred_at: new Date().toISOString(),
      session_id: sessionId,
    };
    if (properties && Object.keys(properties).length > 0) event.properties = properties;
    if (typeof value === 'number') event.value = value;

    buffer.push(event);
    if (buffer.length > maxBufferSize) buffer.splice(0, buffer.length - maxBufferSize);
    if (buffer.length >= flushAt) void flush();
  }

  async function flush(): Promise<void> {
    if (buffer.length === 0) return;

    const batch = buffer;
    buffer = [];

    const token = readCookie('XSRF-TOKEN');

    try {
      const res = await fetch(endpoint, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          ...(token ? { 'X-XSRF-TOKEN': token } : {}),
        },
        body: JSON.stringify({ events: batch }),
      });

      if (!res.ok) {
        // Keep-and-retry: re-queue the batch ahead of newer events, bounded.
        buffer = batch.concat(buffer).slice(-maxBufferSize);
      }
    } catch {
      buffer = batch.concat(buffer).slice(-maxBufferSize);
    }
  }

  function sendBeacon(): void {
    if (buffer.length === 0) return;
    if (typeof navigator === 'undefined' || typeof navigator.sendBeacon !== 'function') return;

    const batch = buffer;
    buffer = [];

    const token = readCookie('XSRF-TOKEN');
    const payload = JSON.stringify({ events: batch, ...(token ? { _token: token } : {}) });
    const blob = new Blob([payload], { type: 'application/json' });

    const ok = navigator.sendBeacon(endpoint, blob);
    if (!ok) buffer = batch.concat(buffer).slice(-maxBufferSize);
  }

  const onVisibility = () => {
    if (typeof document !== 'undefined' && document.visibilityState === 'hidden') sendBeacon();
  };
  const onPageHide = () => sendBeacon();

  if (typeof document !== 'undefined') document.addEventListener('visibilitychange', onVisibility);
  if (typeof window !== 'undefined') window.addEventListener('pagehide', onPageHide);

  if (options.autoPageViews && options.router) {
    options.router.on('navigate', () => enqueue(pageViewEvent));
  }

  return {
    track: enqueue,
    flush,
    destroy() {
      if (timer !== null) clearInterval(timer);
      if (typeof document !== 'undefined') document.removeEventListener('visibilitychange', onVisibility);
      if (typeof window !== 'undefined') window.removeEventListener('pagehide', onPageHide);
    },
  };
}
