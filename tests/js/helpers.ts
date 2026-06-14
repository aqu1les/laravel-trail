import { mock } from 'bun:test';

export interface FakeEnv {
  cookies: Record<string, string>;
  visibilityHandlers: Array<() => void>;
  pagehideHandlers: Array<() => void>;
  fetchMock: ReturnType<typeof mock>;
  beaconMock: ReturnType<typeof mock>;
  storage: Map<string, string>;
  restore: () => void;
}

/**
 * Install fake document/window/navigator/localStorage/fetch globals and return
 * handles the tests use to drive flush triggers and assert transport calls.
 */
export function installFakeEnv(
  opts: { fetchOk?: boolean; fetchStatus?: number; beaconOk?: boolean } = {},
): FakeEnv {
  const cookies: Record<string, string> = {};
  const visibilityHandlers: Array<() => void> = [];
  const pagehideHandlers: Array<() => void> = [];
  const storage = new Map<string, string>();

  const fetchMock = mock(async () => ({
    ok: opts.fetchOk ?? true,
    status: opts.fetchStatus ?? 202,
  }));

  const beaconMock = mock(() => opts.beaconOk ?? true);

  const g = globalThis as Record<string, unknown>;
  const saved = {
    document: g.document,
    window: g.window,
    navigator: g.navigator,
    localStorage: g.localStorage,
    fetch: g.fetch,
  };

  g.document = {
    get cookie() {
      return Object.entries(cookies)
        .map(([k, v]) => `${k}=${v}`)
        .join('; ');
    },
    visibilityState: 'visible',
    addEventListener(type: string, cb: () => void) {
      if (type === 'visibilitychange') visibilityHandlers.push(cb);
    },
    removeEventListener() {},
  };

  g.window = {
    addEventListener(type: string, cb: () => void) {
      if (type === 'pagehide') pagehideHandlers.push(cb);
    },
    removeEventListener() {},
  };

  g.navigator = { sendBeacon: beaconMock };
  g.localStorage = {
    getItem: (k: string) => (storage.has(k) ? storage.get(k)! : null),
    setItem: (k: string, v: string) => void storage.set(k, v),
    removeItem: (k: string) => void storage.delete(k),
  };
  g.fetch = fetchMock as unknown as typeof fetch;

  return {
    cookies,
    visibilityHandlers,
    pagehideHandlers,
    fetchMock,
    beaconMock,
    storage,
    restore() {
      g.document = saved.document;
      g.window = saved.window;
      g.navigator = saved.navigator;
      g.localStorage = saved.localStorage;
      g.fetch = saved.fetch;
    },
  };
}
