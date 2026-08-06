# Detect Navigations from request headers, not from the response

Status: accepted

Automatic page tracking counted every `GET`, which meant Inertia partial reloads, polls, prefetches
and ordinary `fetch` calls all recorded a `page.viewed`. We decided a Navigation is identified
entirely from **request** headers: the Inertia and Livewire markers first, then `Sec-Fetch-Dest`,
falling back to `Accept` when the client sends no Fetch Metadata. The rule never inspects the
response.

## Considered options

**Sniffing the response** (count it if the response is an HTML document, optionally restricted by
status) was designed first and rejected. `Content-Type` is a proxy for "the user landed on a page",
and proxies leak: a `302` is `text/html`, so is a 404, so the rule needed a status patch to be even
approximately right. `Sec-Fetch-Dest: document` is not a proxy, it is the browser stating the
request is a top-level navigation, and it also catches browser-level prefetch, which no amount of
response sniffing could.

**`X-Requested-With: XMLHttpRequest`** looks like the obvious signal for "this is not a navigation"
and is a trap. Inertia sets it on *every* request, including real navigations, so filtering on it
would delete nearly all page views in an Inertia app. Do not reintroduce it.

## Consequences

- **Redirects count twice.** A `GET` answering `302` is itself a top-level navigation, and so is its
  destination. Filtering it would mean reading the response status, which this decision rules out of
  the default path. This was chosen knowingly: `Trail::trackPageViewsUsing()` receives the
  `Response` precisely so an app can filter on status if it wants to.
- **Livewire hover prefetch counts as a Navigation.** `wire:navigate.hover` prefetches through the
  same function, with the same headers, as a real navigation (`livewire/livewire` v4.3.1,
  `dist/livewire.js`, `prefetchHtml()` calls `performFetch()` calls `sendNavigateRequest()`). There
  is no header to filter on. Inertia, by contrast, sends `Purpose: prefetch`.
- **Clients without Fetch Metadata rely on the `Accept` fallback.** This is load-bearing, not a
  nicety: Laravel's own test requests send no `Sec-Fetch-*`, and Symfony's `Request::create()` fills
  in `Accept: text/html,...`. Making the missing-header case restrictive instead of a fallback would
  fail the entire test suite and silently stop counting for any non-browser client.
- Recorded counts change for existing users of `auto_track.page_views`: Inertia and XHR-heavy apps
  count less, `wire:navigate` apps count more, because they were counting almost nothing before.

See `CONTEXT.md` for the definition of Navigation.
