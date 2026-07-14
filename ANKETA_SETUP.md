# Lokalna anketa — regija → občina → anketa

Laravel + Blade app. A visitor picks a **statistical region on an interactive map**,
the map zooms into that region, they pick their **municipality**, then fill in a
survey whose questions are fully **data-driven and extensible**. Responses are
stored in SQLite. Styling uses **Tailwind CSS v4** (via Vite).

## Run it

```bash
cd LokalnaPolitikaAnketa
composer install                 # if vendor/ is missing
cp .env.example .env             # if you don't have .env yet
php artisan key:generate         # if APP_KEY is empty

touch database/database.sqlite
php artisan migrate --seed       # tables + 12 regions, 212 municipalities, demo survey

npm install
npm run build                    # compile Tailwind + JS (or `npm run dev` while developing)

php artisan serve
```

Open http://localhost:8000 — pick a region on the map, then a municipality, then answer.

> Tailwind is required for styling now: run `npm run build` (production) or
> `npm run dev` (hot reload). The compiled assets are loaded via `@vite(...)`.

Results dashboard: http://localhost:8000/rezultati (interpret results) and
`/rezultati/izvoz.csv` (download all responses as CSV, one column per question).

Both are protected by **HTTP Basic auth**. Set credentials in `.env`:

```
SURVEY_ADMIN_USER=admin
SURVEY_ADMIN_PASSWORD=some-strong-password
```

The browser will prompt for them. If either is left empty, the dashboard is
reachable only in the `local` environment (handy for development). Always serve
over HTTPS in production so the credentials aren't sent in the clear.

## The map

The map geometry is already imported: `public/data/slovenia_map.json` (the file the
frontend renders, ~150 KB) plus cached `svg_path` / `centroid` on each municipality
row in the DB. It was generated from the GURS **OB.geojson** (občine, CC-BY,
Geodetska uprava RS).

To regenerate it (e.g. after boundary changes) there's an artisan command:

```bash
php artisan map:import OB.geojson
# optional: --regions=SR.geojson to derive region membership from geometry
```

Region grouping (12 statistical regions) comes from the authoritative mapping in
`database/data/regions.json`, matched to `OB_UIME`. The map colors municipalities by
region; hovering a region highlights it, clicking zooms in to pick a municipality.

## Editing / extending the survey

Questions live in the DB and are defined in `database/data/survey.json`
(re-seed with `php artisan db:seed --class=SurveySeeder`). Supported question
`type`s (add more without schema changes):

`section`, `text`, `textarea`, `email`, `tel`, `number`, `date`,
`radio`, `select`, `checkbox`, `scale`, `boolean`.

Each question has: `key`, `type`, `label`, `help_text`, `placeholder`,
`options` (`[{value,label}]`), `config` (e.g. `{min,max,step,rows,maxlength}`),
`is_required`, `sort`. Validation rules are generated per-question in
`App\Models\Question::validationRules()`, and the form + admin aggregation are
generic, so new questions and even new surveys need no code changes. Multiple
surveys are supported (`surveys` table + `is_active`).

> The seeded survey is a **placeholder** local-politics questionnaire in Slovene.
> Swap in the real questions by editing `survey.json` and re-seeding.

## Data model

- `regions` (12) → `municipalities` (212)
- `surveys` → `questions`
- `responses` (→ municipality) → `answers` (→ question, value as JSON)
- IPs are stored only as a salted hash; no names required.
