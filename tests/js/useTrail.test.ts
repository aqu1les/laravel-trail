import { afterEach, expect, it } from 'bun:test';
import { installFakeEnv } from './helpers';
import { useTrail, resetTrail } from '../../resources/js/trail/useTrail';

let env: ReturnType<typeof installFakeEnv> | null = null;

afterEach(() => {
  resetTrail();
  env?.restore();
  env = null;
});

it('returns the same client instance across calls', () => {
  env = installFakeEnv();
  const a = useTrail({ flushInterval: 0 });
  const b = useTrail();
  expect(a).toBe(b);
  a.destroy();
});
