import { createTrail } from './createTrail';
import type { TrailClient, TrailOptions } from './types';

let instance: TrailClient | null = null;

/**
 * Return the singleton Trail client, creating it on first call. A thin Vue
 * "composable" - it intentionally has no Vue dependency, so it also works in
 * plain modules and tests.
 */
export function useTrail(options: TrailOptions = {}): TrailClient {
  if (instance === null) {
    instance = createTrail(options);
  }
  return instance;
}

/** Reset the singleton (tests / HMR). */
export function resetTrail(): void {
  instance?.destroy();
  instance = null;
}
