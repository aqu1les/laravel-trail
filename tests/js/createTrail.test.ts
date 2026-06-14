import { afterEach, expect, it } from 'bun:test';
import { installFakeEnv } from './helpers';
import { createTrail } from '../../resources/js/trail/createTrail';

let env: ReturnType<typeof installFakeEnv> | null = null;

afterEach(() => {
  env?.restore();
  env = null;
});

it('flushes via fetch when the buffer reaches flushAt', async () => {
  env = installFakeEnv();
  env.cookies['XSRF-TOKEN'] = encodeURIComponent('tok-123');

  const client = createTrail({ flushAt: 2, flushInterval: 0 });

  client.track('a.one');
  expect(env.fetchMock).toHaveBeenCalledTimes(0);

  client.track('a.two'); // hits flushAt -> async flush
  await Promise.resolve();
  await Promise.resolve();

  expect(env.fetchMock).toHaveBeenCalledTimes(1);

  const [url, init] = env.fetchMock.mock.calls[0] as [string, RequestInit];
  expect(url).toBe('/trail/api/ingest');
  expect((init.headers as Record<string, string>)['X-XSRF-TOKEN']).toBe('tok-123');

  const body = JSON.parse(init.body as string);
  expect(body.events).toHaveLength(2);
  expect(body.events[0].name).toBe('a.one');
  expect(typeof body.events[0].occurred_at).toBe('string');
  expect(typeof body.events[0].session_id).toBe('string');

  client.destroy();
});

it('sends a beacon on pagehide', () => {
  env = installFakeEnv();
  env.cookies['XSRF-TOKEN'] = encodeURIComponent('beacon-tok');

  const client = createTrail({ flushAt: 99, flushInterval: 0 });
  client.track('leaving.page');

  env.pagehideHandlers.forEach((h) => h());

  expect(env.beaconMock).toHaveBeenCalledTimes(1);

  const [url, blob] = env.beaconMock.mock.calls[0] as [string, Blob];
  expect(url).toBe('/trail/api/ingest');
  expect(blob).toBeInstanceOf(Blob);

  client.destroy();
});

it('beacons on visibilitychange to hidden', () => {
  env = installFakeEnv();
  const fakeDoc = (globalThis as Record<string, unknown>).document as { visibilityState: string };
  fakeDoc.visibilityState = 'hidden';

  const client = createTrail({ flushAt: 99, flushInterval: 0 });
  client.track('hidden.event');

  env.visibilityHandlers.forEach((h) => h());

  expect(env.beaconMock).toHaveBeenCalledTimes(1);
  client.destroy();
});

it('includes _token in the beacon payload', async () => {
  env = installFakeEnv();
  env.cookies['XSRF-TOKEN'] = encodeURIComponent('payload-tok');

  const client = createTrail({ flushAt: 99, flushInterval: 0 });
  client.track('bye');
  env.pagehideHandlers.forEach((h) => h());

  const [, blob] = env.beaconMock.mock.calls[0] as [string, Blob];
  const parsed = JSON.parse(await blob.text());

  expect(parsed._token).toBe('payload-tok');
  expect(parsed.events[0].name).toBe('bye');

  client.destroy();
});
