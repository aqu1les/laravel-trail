# Context

Glossary of domain terms for `laravel-trail`. Use these words in code, tests, issues
and documentation. This file is a glossary, not a spec: it says what things mean, never
how they are built.

## Navigation

The document load or Inertia visit that changes the page the Actor is looking at.

A Navigation is what the package's automatic page tracking exists to count. Everything
else the browser fetches on an Actor's behalf is not a Navigation, even when it is a
`GET` and even when it returns a full page of markup.

These are **not** Navigations:

- **Partial reload** - an Inertia request that refreshes some props of the page the
  Actor is already on, including deferred props and infinite scroll.
- **Poll** - a request issued on a timer to refresh the current page. The Actor did
  not go anywhere.
- **Prefetch** - a request speculatively issued for a page the Actor might visit next.
  It may never be visited at all.
- **Background fetch** - any `fetch`/`XHR` an already-loaded page makes for data:
  API calls, autocomplete, lazy dropdowns, downloads.

Avoid **page view** in new prose and in new identifiers. It reads as "a page an Actor
viewed" but has historically meant "any `GET` request", and that gap is the reason this
term exists. The event name `page.viewed` stays as-is for backwards compatibility.

## Actor

The subject an Event is attributed to: the Eloquent model recorded as the event's
polymorphic subject, or an anonymous session when there is none.

## Event

A single recorded fact about what an Actor did, with a name, optional properties,
optional numeric value, and captured context.

## Path

The ordered sequence of Events a single Actor produced after a given start Event.
One Path belongs to one Actor.
