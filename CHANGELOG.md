# Changelog

All notable changes to `laravel-trail` will be documented in this file.

## Unreleased

### Added

- Browser / SPA event capture. A plain `composer require` now ships a batch ingestion endpoint
  (`POST /<path>/api/ingest`), and `php artisan vendor:publish --tag=trail-js` publishes a batched,
  queue-safe TypeScript client (`createTrail` / `useTrail`).
- Separate view and write gates: `Trail::auth` guards the dashboard and read API, `Trail::ingestUsing`
  guards the browser write endpoint (default: allow, including anonymous).
- `Trail::routes()` plus the `register_routes`, `api`, and `browser` config keys for taking over route
  registration and tuning ingestion (recorder, batch size, rate limit, event allowlist).
- `PendingEvent::at()` and `PendingEvent::usingRecorder()` for setting a custom `occurred_at` and
  selecting a recorder by name.

## v1.0.3 - 2026-06-12

**Full Changelog**: https://github.com/aqu1les/laravel-trail/compare/v1.0.2...v1.0.3

## v1.0.2 - 2026-06-11

### What's Changed

* Footer dinamico e customizavel na sidebar do dashboard by @aqu1les in https://github.com/aqu1les/laravel-trail/pull/2
* Page views: captura de UTM e toggle para esconder by @aqu1les in https://github.com/aqu1les/laravel-trail/pull/1

### New Contributors

* @aqu1les made their first contribution in https://github.com/aqu1les/laravel-trail/pull/2

**Full Changelog**: https://github.com/aqu1les/laravel-trail/compare/v1.0.1...v1.0.2

## v1.0.1 - 2026-06-11

**Full Changelog**: https://github.com/aqu1les/laravel-trail/compare/v1.0.0...v1.0.1
