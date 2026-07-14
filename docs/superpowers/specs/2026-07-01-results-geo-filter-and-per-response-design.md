# Results dashboard: geo filtering + per-response view

**Date:** 2026-07-01
**Status:** Approved (design), pending implementation plan

## Goal

Extend the admin results dashboard (`/rezultati`) with:

1. **Per-region / per-municipality filtering** of all aggregates, and a CSV
   export that respects the selected area.
2. **A per-response "Individual" view** (Google Forms style): page through
   submitted responses one at a time, seeing every question and that
   respondent's answer.

All of it stays **admin-only**, behind the existing `auth` middleware (Keycloak SSO).
Nothing is public.

## Non-goals

- No drill-down URL hierarchy (`/rezultati/regija/...`). Filtering is done with
  query params on the existing route.
- No public per-municipality results.
- No automated tests (verified manually).
- No changes to how responses are submitted or stored.

## Routes

All within the existing `Route::middleware('auth')` group in
`routes/web.php`:

```
GET /rezultati                      → ResultsController@index        (accepts ?region=&obcina=)
GET /rezultati/izvoz.csv            → ResultsController@export        (accepts ?region=&obcina=)
GET /rezultati/odgovor/{response}   → ResultsController@showResponse  (honors ?region=&obcina=)
```

## Geo filter (query-param approach)

A small helper `App\Support\ResultsFilter` centralizes filter parsing and
application so `index()`, `export()`, and `showResponse()` all scope data
through one code path.

**Input:** `?region=<region-slug>&obcina=<municipality-slug>`.

**Resolution rules:**
- Look up the region and municipality by slug (from the cached
  `Region::cached()` set — no extra query).
- If a municipality is selected, it is the effective scope (most specific).
- If only a region is selected, scope to that region.
- Unknown / invalid slugs are ignored (fall back to unfiltered) — never an error.

**Responsibilities:**
- `apply(Builder $query): Builder` — adds the geo `WHERE` to any query built on
  `responses` (join to `municipalities`, filter by `municipality_id`, or by
  `region_id` via the municipalities join). No-op when nothing is selected.
- Expose the selected `Region` / `Municipality` models for the view (dropdown
  pre-selection, headings) and for the CSV filename.

Every existing aggregate in `index()` — `$total`, `$byRegion`, `$aggregates`,
`$scales`, `$recent` — is built through `ResultsFilter::apply()`. With no params
present, output is identical to today.

## Dashboard UI (dropdowns)

Two native `<select>` elements in the dashboard header, submitting via `GET`
(works without JS, matching the app's progressive-enhancement pattern):

- **Region** dropdown — options from `Region::cached()`.
- **Municipality** dropdown — narrowed to the selected region's municipalities.
  A small inline script does the client-side narrowing when a region is picked;
  it also works without JS (server renders the correct list on reload).
- Selecting a region reloads with its municipalities listed; municipality is the
  most specific scope when both are set.
- The "Izvozi CSV" link carries the current query params, so the export matches
  what is on screen.

## Per-response "Individual" view

`showResponse(Response $response)`:

- 404 if the response does not belong to the active survey.
- Loads the response with `municipality.region` and `answers` (keyed by
  `question_key`), plus the cached active `Survey` with its questions.
- Renders each non-section question with that respondent's answer, reusing the
  existing value-formatting logic (`formatValue()`): labels for choice values,
  "Da/Ne" for booleans, `;`-joined arrays for multi-selects.
- **Prev / Next** navigation within the current filter, ordered by
  `submitted_at`, via `WHERE submitted_at < ? / > ?` lookups (no loading all
  IDs). Shows an "N of M" counter. Prev/next links carry the active filter
  params. Arrows disable at the ends.
- Entry point: each row in the existing "Zadnji odgovori" table links to its
  detail page.

## Shared value formatting

The `formatValue(Question, $value)` logic currently private to
`ResultsController::export()` is extracted so the CSV export and the
per-response view render answers identically. It moves into the shared layer
(a static helper, e.g. on `ResultsFilter`'s neighbor or a dedicated
`App\Support\AnswerFormatter`) and both call sites use it.

## Data flow & safety

- All queries are scoped by `survey_id` first, then the optional geo filter — a
  response from another survey or outside the filter cannot appear.
- `showResponse` 404s for responses outside the active survey.
- Invalid query-param slugs are ignored rather than erroring.
- These views are **not cached**: response data changes as submissions arrive.
  (This is intentionally separate from the fixed-data caching layer, which
  covers survey/questions/regions/geometry only.)

## Files touched (anticipated)

- `routes/web.php` — one new route.
- `app/Http/Controllers/ResultsController.php` — `index`/`export` use the filter;
  new `showResponse`; `formatValue` extracted.
- `app/Support/ResultsFilter.php` — new.
- `app/Support/AnswerFormatter.php` — new (extracted formatting), or co-located.
- `resources/views/results/index.blade.php` — filter dropdowns, response-row links.
- `resources/views/results/response.blade.php` — new per-response view.