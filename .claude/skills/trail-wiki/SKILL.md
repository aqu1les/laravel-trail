---
name: trail-wiki
description: Use when updating, editing, or adding pages to the laravel-trail GitHub Wiki — especially after a roadmap feature ships and a "Coming soon" page needs to move to "Shipped".
---

# Updating the Trail Wiki

The Trail wiki is the user-facing docs for people who install `aqu1les/laravel-trail` via Composer. Audience: **library consumers**, not contributors. Document usage, not internal architecture.

- **Public URL:** https://github.com/aqu1les/laravel-trail/wiki
- **Wiki git remote:** `git@github.com:aqu1les/laravel-trail.wiki.git` (a *separate* repo from the code)
- **Local clone (source of truth):** `/home/aqu1les/laravel-trail.wiki/` — branch `master`
- **Original drafts (may be stale, ignore for edits):** `/home/aqu1les/laravel-trail-wiki/`

The wiki repo only exists after the Home page is created once in the GitHub UI (Settings → Features → ✅ Wikis). That's already done.

## The golden rule: shipped vs. Coming soon

**Document only what is actually shipped.** Anything on the roadmap is marked **Coming soon** — never describe an unbuilt feature as if it works.

Before writing that a feature exists, verify it against the sources of truth (in priority order):

1. `src/**` — the actual code. A method existing on the facade isn't enough; check the driver/controller behind it really does the work.
2. `README.md` — the shipped × roadmap table and the tone to mirror.
3. `docs/superpowers/plans/README.md` — the frozen API contract and per-plan status.

When the code and README disagree (README lags behind merged code), the **code wins** — but only count committed code, not uncommitted working-tree changes from a parallel session.

## When a feature ships, update two places

1. **`Roadmap-&-Status.md`** — move the line from "Coming soon" (⏳) to "Shipped" (✅).
2. **The page that describes it** — remove the "Coming soon" marker and document the real behavior with a runnable example.

Pages that currently carry "Coming soon" markers to revisit as things land:

| Feature | Pages to update when shipped |
| --- | --- |
| `ingest` recorder | `Recording-Modes.md`, `Configuration.md` |
| Aggregates + `trail:aggregate`/`trail:prune`/`trail:install` | `Configuration.md`, `Privacy.md` (retention) |
| Automatic context capture + page views | `Configuration.md` (`auto_track`), `Tracking-Events.md` |
| Visual dashboard (Overview/Events/Funnels/Timeline) | `The-Dashboard.md`, `Theming-&-Design-System.md` |
| ClickHouse / pluggable storage | `Configuration.md`, `Roadmap-&-Status.md` |

## Update workflow

```bash
cd /home/aqu1les/laravel-trail.wiki
git pull origin master            # someone may have edited on github.com
# edit the .md files
git add -A
git commit -m "Document <thing>"
git push origin master            # live on the wiki immediately — confirm with user first
```

Changes are public the instant you push. **Get the user's OK before pushing.**

## Page conventions

- **Filename = page title**, spaces become hyphens: `Quick-Start.md` → "Quick Start". `&` stays literal (`Roadmap-&-Status.md`).
- **`Home.md`** is the landing page. **`_Sidebar.md`** controls the left nav (grouped: Getting Started · Usage · Dashboard · Reference). **`_Footer.md`** is the footer line.
- Link between pages with `[[Page Title]]` (wiki link syntax), e.g. `[[Configuration]]`.
- Adding a new page? Add it to `_Sidebar.md` under the right group, or it won't be discoverable.

## Writing style (mirror README.md)

- English. Human and technical — the same voice as `README.md`.
- Short sentences, active voice. No AI fluff, no "in this guide we will…", no decorative emoji.
- Start each page directly on useful content. No preamble.
- Every code block must be real and runnable — copy/adapt from `README.md` and `src/**`.
- Event names in lowercase `dot.case` (`order.placed`). Actors = a model instance.
- `value` serializes as a **string** in the JSON API (decimal:4 cast) — keep the `parseFloat` note. Dates are ISO-8601.

## Common mistakes

- Documenting a working-tree feature that isn't committed yet → it's not shipped. Check `git log`, not just the files.
- Adding a page but forgetting `_Sidebar.md` → orphaned page.
- Editing the stale drafts in `/home/aqu1les/laravel-trail-wiki/` instead of the clone → changes never reach GitHub.
- Pushing before the user reviews → the wiki is public; confirm first.
