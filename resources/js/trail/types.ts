export type TrailProperties = Record<string, unknown>;

export interface TrailEventInput {
  name: string;
  properties?: TrailProperties;
  value?: number;
  occurred_at: string;
  session_id: string;
}

export interface InertiaLikeRouter {
  on(event: 'navigate', cb: (...args: unknown[]) => void): void;
}

export interface TrailOptions {
  /** Ingest endpoint. Default: '/trail/api/ingest'. */
  endpoint?: string;
  /** Flush when the buffer reaches this many events. Default: 20. */
  flushAt?: number;
  /** Flush every this many milliseconds. Default: 5000. */
  flushInterval?: number;
  /** Hard cap on buffered events; oldest dropped beyond it. Default: 1000. */
  maxBufferSize?: number;
  /** Emit a page-view event on Inertia navigations. Default: false. */
  autoPageViews?: boolean;
  /** Page-view event name when autoPageViews is on. Default: 'page.viewed'. */
  pageViewEvent?: string;
  /** Inertia router, required to wire autoPageViews. */
  router?: InertiaLikeRouter;
}

export interface TrailClient {
  track(name: string, properties?: TrailProperties, value?: number): void;
  flush(): Promise<void>;
  /** Tear down timers and listeners (mainly for tests / HMR). */
  destroy(): void;
}
