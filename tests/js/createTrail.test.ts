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
