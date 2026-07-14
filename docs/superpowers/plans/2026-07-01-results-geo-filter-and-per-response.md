# Results Dashboard: Geo Filter + Per-Response View — Implementation Plan

> **For agentic workers:** Steps use checkbox (`- [ ]`) syntax for tracking.
> **No automated tests** (per user instruction) — verification is manual via curl/browser.
> **Not a git repo** — no commit steps.

**Goal:** Add region/municipality filtering (dropdowns + CSV) and a Google-Forms-style per-response detail view to the admin `/rezultati` dashboard.

**Architecture:** Query-param filter (`?region=&obcina=`) applied to every existing aggregate query through one shared `ResultsFilter` helper. Answer value formatting extracted to a shared `AnswerFormatter` so the CSV and the per-response view render identically. A new `showResponse` action with prev/next navigation scoped to the active filter.

**Tech Stack:** Laravel 11, Blade, Tailwind v4, SQLite. All results routes are behind the `auth` (Keycloak SSO) middleware group in `routes/web.php`.

---

## File Structure

- **Create** `app/Support/AnswerFormatter.php` — formats a stored answer value into a human string (labels for choices, "Da/Ne" for booleans, `;`-joined arrays). Used by CSV export and per-response view.
- **Create** `app/Support/ResultsFilter.php` — parses `?region=&obcina=`, resolves Region/Municipality, applies the geo `WHERE` to any responses query, and carries params for links/CSV filename.
- **Create** `resources/views/results/response.blade.php` — per-response detail view with prev/next.
- **Modify** `app/Http/Controllers/ResultsController.php` — `index`/`export` use `ResultsFilter`; extract `formatValue` to `AnswerFormatter`; add `showResponse`.
- **Modify** `resources/views/results/index.blade.php` — filter dropdowns + link each response row to its detail page.
- **Modify** `routes/web.php` — add the `results.response` route inside the `auth` group.

---

## Task 1: AnswerFormatter (extract existing formatting)

**Files:**
- Create: `app/Support/AnswerFormatter.php`
- Modify: `app/Http/Controllers/ResultsController.php` (remove private `formatValue`, delegate to helper)

- [ ] **Step 1: Create the formatter**

Create `app/Support/AnswerFormatter.php`. This is the current `ResultsController::formatValue` logic, made static and reusable:

```php
<?php

namespace App\Support;

use App\Models\Question;

class AnswerFormatter
{
    /** Format a stored answer value (already JSON-decoded) into a human string. */
    public static function format(Question $question, mixed $value): string
    {
        $labels = collect($question->options ?? [])->pluck('label', 'value');
        $map = fn ($v) => is_bool($v)
            ? ($v ? 'Da' : 'Ne')
            : ($labels[(string) $v] ?? (string) $v);

        if (is_array($value)) {
            return implode('; ', array_map($map, $value));
        }

        return $map($value);
    }
}
```

- [ ] **Step 2: Delegate in the controller**

In `app/Http/Controllers/ResultsController.php`, delete the private `formatValue` method (lines ~130-139) and replace the call inside `export()`:

Find:
```php
$row[] = $answer ? $this->formatValue($q, $answer->value) : '';
```
Replace with:
```php
$row[] = $answer ? AnswerFormatter::format($q, $answer->value) : '';
```

Add the import at the top of the controller (with the other `use` statements):
```php
use App\Support\AnswerFormatter;
```

- [ ] **Step 3: Verify no syntax errors**

Run: `php -l app/Support/AnswerFormatter.php && php -l app/Http/Controllers/ResultsController.php`
Expected: `No syntax errors detected` for both.

- [ ] **Step 4: Verify CSV still works**

Run (server on :8899, logged-in session not required for `php -l`; for live check use browser). Minimal check that the class resolves and formats:
```bash
php artisan tinker --execute='
use App\Support\AnswerFormatter;
use App\Models\Question;
$q = Question::where("type","radio")->first();
echo AnswerFormatter::format($q, $q->options[0]["value"] ?? "x").PHP_EOL;
echo AnswerFormatter::format($q, ["a","b"]).PHP_EOL;   // multi -> "a; b" (or mapped labels)
echo AnswerFormatter::format($q, true).PHP_EOL;         // -> "Da"
'
```
Expected: prints the mapped label, a `;`-joined string, and `Da` — no errors.

---

## Task 2: ResultsFilter helper

**Files:**
- Create: `app/Support/ResultsFilter.php`

- [ ] **Step 1: Create the filter**

Create `app/Support/ResultsFilter.php`:

```php
<?php

namespace App\Support;

use App\Models\Municipality;
use App\Models\Region;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Http\Request;

/**
 * Parses ?region=<slug>&obcina=<slug> and scopes results queries by area.
 * Municipality wins as the most specific scope. Unknown slugs are ignored.
 */
class ResultsFilter
{
    public ?Region $region = null;
    public ?Municipality $municipality = null;

    public function __construct(Request $request)
    {
        $regionSlug = $request->query('region');
        $muniSlug = $request->query('obcina');

        if ($regionSlug) {
            $this->region = Region::where('slug', $regionSlug)->first();
        }
        if ($muniSlug) {
            $this->municipality = Municipality::where('slug', $muniSlug)->first();
            // keep region in sync with the chosen municipality
            if ($this->municipality) {
                $this->region = $this->municipality->region;
            }
        }
    }

    public function active(): bool
    {
        return $this->municipality !== null || $this->region !== null;
    }

    /**
     * Apply the geo scope to a query built on the `responses` table.
     * No-op when nothing is selected.
     */
    public function apply(Builder $query): Builder
    {
        if ($this->municipality) {
            return $query->where('responses.municipality_id', $this->municipality->id);
        }
        if ($this->region) {
            return $query->whereIn(
                'responses.municipality_id',
                Municipality::where('region_id', $this->region->id)->select('id')
            );
        }
        return $query;
    }

    /** Query-string params for building links / preserving the filter. */
    public function params(): array
    {
        return array_filter([
            'region' => $this->region?->slug,
            'obcina' => $this->municipality?->slug,
        ]);
    }

    /** Suffix for the CSV filename, e.g. "-ljubljana" or "". */
    public function filenameSuffix(): string
    {
        if ($this->municipality) return '-' . $this->municipality->slug;
        if ($this->region) return '-' . $this->region->slug;
        return '';
    }
}
```

- [ ] **Step 2: Verify no syntax errors**

Run: `php -l app/Support/ResultsFilter.php`
Expected: `No syntax errors detected`.

- [ ] **Step 3: Verify parsing + apply against real data**

```bash
php artisan tinker --execute='
use App\Support\ResultsFilter;
use App\Models\Response;
use Illuminate\Http\Request;
$muni = App\Models\Municipality::first();
$f = new ResultsFilter(Request::create("/rezultati","GET",["obcina"=>$muni->slug]));
echo "muni: ".($f->municipality?->name ?? "null")." region: ".($f->region?->name ?? "null")."\n";
$sql = $f->apply(Response::query())->toSql();
echo "sql: $sql\n";
echo "params: ".json_encode($f->params())." suffix: ".$f->filenameSuffix()."\n";
$bad = new ResultsFilter(Request::create("/rezultati","GET",["region"=>"does-not-exist"]));
echo "invalid slug active(): ".var_export($bad->active(), true)."\n";  // false
'
```
Expected: resolves the municipality + its region, SQL contains `where "responses"."municipality_id" =`, params/suffix reflect the muni slug, and the invalid slug yields `active(): false`.

---

## Task 3: Wire the filter into index() and export()

**Files:**
- Modify: `app/Http/Controllers/ResultsController.php`

- [ ] **Step 1: Accept Request + build the filter in `index()`**

Change the `index()` signature and first lines. Find:
```php
    public function index()
    {
        $survey = Survey::active();
        abort_if(! $survey, 404);
        $survey->loadMissing('questions');

        $total = SurveyResponse::where('survey_id', $survey->id)->count();
```
Replace with:
```php
    public function index(Request $request)
    {
        $survey = Survey::active();
        abort_if(! $survey, 404);
        $survey->loadMissing('questions');

        $filter = new ResultsFilter($request);

        $total = $filter->apply(SurveyResponse::where('survey_id', $survey->id))->count();
```

- [ ] **Step 2: Scope `$byRegion`**

Find:
```php
        $byRegion = SurveyResponse::where('responses.survey_id', $survey->id)
            ->join('municipalities', 'municipalities.id', '=', 'responses.municipality_id')
            ->join('regions', 'regions.id', '=', 'municipalities.region_id')
            ->select('regions.name', DB::raw('count(*) as c'))
            ->groupBy('regions.name')
            ->orderByDesc('c')
            ->pluck('c', 'regions.name');
```
Replace the first line so the filter applies (the rest is unchanged):
```php
        $byRegion = $filter->apply(SurveyResponse::where('responses.survey_id', $survey->id))
            ->join('municipalities', 'municipalities.id', '=', 'responses.municipality_id')
            ->join('regions', 'regions.id', '=', 'municipalities.region_id')
            ->select('regions.name', DB::raw('count(*) as c'))
            ->groupBy('regions.name')
            ->orderByDesc('c')
            ->pluck('c', 'regions.name');
```

- [ ] **Step 3: Scope the per-question aggregate query**

Find:
```php
            $rows = DB::table('answers')
                ->join('responses', 'responses.id', '=', 'answers.response_id')
                ->where('responses.survey_id', $survey->id)
                ->where('answers.question_id', $q->id)
                ->pluck('answers.value');
```
Replace with (applies filter to the joined `responses`):
```php
            $rows = $filter->apply(
                    DB::table('answers')
                        ->join('responses', 'responses.id', '=', 'answers.response_id')
                        ->where('responses.survey_id', $survey->id)
                        ->where('answers.question_id', $q->id)
                )
                ->pluck('answers.value');
```

- [ ] **Step 4: Scope `$recent`**

Find:
```php
        $recent = SurveyResponse::where('survey_id', $survey->id)
            ->with('municipality.region')
            ->latest('submitted_at')
            ->limit(50)
            ->get();

        return view('results.index', compact('survey', 'total', 'byRegion', 'aggregates', 'scales', 'recent'));
```
Replace with:
```php
        $recent = $filter->apply(SurveyResponse::where('survey_id', $survey->id))
            ->with('municipality.region')
            ->latest('submitted_at')
            ->limit(50)
            ->get();

        $regions = Region::cached();

        return view('results.index', compact(
            'survey', 'total', 'byRegion', 'aggregates', 'scales', 'recent', 'filter', 'regions'
        ));
```

- [ ] **Step 5: Accept Request + filter in `export()`**

Find:
```php
    public function export(): StreamedResponse
    {
        $survey = Survey::active();
        abort_if(! $survey, 404);
        $survey->loadMissing('questions');

        $questions = $survey->questions->reject(fn (Question $q) => $q->isSection())->values();
        $responses = SurveyResponse::where('survey_id', $survey->id)
            ->with(['municipality.region', 'answers'])
            ->orderBy('submitted_at')
            ->get();

        $filename = 'anketa-' . $survey->slug . '-' . now()->format('Ymd-His') . '.csv';
```
Replace with:
```php
    public function export(Request $request): StreamedResponse
    {
        $survey = Survey::active();
        abort_if(! $survey, 404);
        $survey->loadMissing('questions');

        $filter = new ResultsFilter($request);

        $questions = $survey->questions->reject(fn (Question $q) => $q->isSection())->values();
        $responses = $filter->apply(SurveyResponse::where('survey_id', $survey->id))
            ->with(['municipality.region', 'answers'])
            ->orderBy('submitted_at')
            ->get();

        $filename = 'anketa-' . $survey->slug . $filter->filenameSuffix() . '-' . now()->format('Ymd-His') . '.csv';
```

- [ ] **Step 6: Add imports**

At the top of `app/Http/Controllers/ResultsController.php`, add (with existing `use` lines):
```php
use App\Models\Region;
use App\Support\ResultsFilter;
use Illuminate\Http\Request;
```

- [ ] **Step 7: Verify no syntax errors**

Run: `php -l app/Http/Controllers/ResultsController.php`
Expected: `No syntax errors detected`.

- [ ] **Step 8: Verify filtered counts differ from unfiltered**

```bash
php artisan tinker --execute='
use App\Support\ResultsFilter;
use App\Models\Response;
use App\Models\Survey;
use Illuminate\Http\Request;
$s = Survey::active();
$all = Response::where("survey_id",$s->id)->count();
$reg = App\Models\Region::whereHas("municipalities.responses")->first();
if (!$reg) { echo "no responses yet — skip\n"; exit; }
$f = new ResultsFilter(Request::create("/","GET",["region"=>$reg->slug]));
$scoped = $f->apply(Response::where("survey_id",$s->id))->count();
echo "all: $all  region {$reg->slug}: $scoped  (scoped <= all: ".($scoped<=$all?"ok":"BAD").")\n";
'
```
Expected: `scoped <= all: ok`. (If there are zero responses, it prints "skip" — acceptable.)

---

## Task 4: showResponse action (per-response detail + prev/next)

**Files:**
- Modify: `app/Http/Controllers/ResultsController.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Add the route**

In `routes/web.php`, inside the `Route::middleware('auth')->group(...)` block, add after the export route:
```php
    Route::get('/rezultati/odgovor/{response}', [ResultsController::class, 'showResponse'])->name('results.response');
```

- [ ] **Step 2: Add the action**

In `app/Http/Controllers/ResultsController.php`, add this method (after `index`, before `export`):
```php
    public function showResponse(Request $request, SurveyResponse $response): View
    {
        $survey = Survey::active();
        abort_if(! $survey, 404);
        abort_if($response->survey_id !== $survey->id, 404);

        $survey->loadMissing('questions');
        $response->load(['municipality.region', 'answers']);

        $filter = new ResultsFilter($request);
        $base = fn () => $filter->apply(SurveyResponse::where('survey_id', $survey->id));

        // Prev = older (earlier submitted_at), Next = newer — matches list order (latest first).
        $prev = (clone $base())
            ->where('submitted_at', '<', $response->submitted_at)
            ->orderByDesc('submitted_at')
            ->value('id');
        $next = (clone $base())
            ->where('submitted_at', '>', $response->submitted_at)
            ->orderBy('submitted_at')
            ->value('id');

        $total = $base()->count();
        $position = $base()
            ->where('submitted_at', '>', $response->submitted_at)
            ->count() + 1; // newest = 1

        $byKey = $response->answers->keyBy('question_key');

        return view('results.response', [
            'survey' => $survey,
            'response' => $response,
            'byKey' => $byKey,
            'filter' => $filter,
            'prevId' => $prev,
            'nextId' => $next,
            'position' => $position,
            'total' => $total,
        ]);
    }
```

Note: `$base()` returns a fresh Eloquent builder each call; `clone` guards the extra `where` from leaking between prev/next.

- [ ] **Step 3: Ensure `View` is imported**

`use Illuminate\View\View;` — confirm it's present at the top of the controller (it is used by other typed returns in the app; add it if missing).

Run: `grep -n "use Illuminate\\\\View\\\\View;" app/Http/Controllers/ResultsController.php || echo "MISSING — add it"`
If missing, add `use Illuminate\View\View;` with the other imports.

- [ ] **Step 4: Verify no syntax errors**

Run: `php -l app/Http/Controllers/ResultsController.php`
Expected: `No syntax errors detected`.

- [ ] **Step 5: Verify route registered**

Run: `php artisan route:list --name=results.response`
Expected: one row showing `GET|HEAD  rezultati/odgovor/{response} ... results.response`.

---

## Task 5: response.blade.php (per-response view)

**Files:**
- Create: `resources/views/results/response.blade.php`

- [ ] **Step 1: Create the view**

Create `resources/views/results/response.blade.php`:

```blade
@extends('layouts.app')
@section('title', 'Odgovor')

@php use App\Support\AnswerFormatter; @endphp

@section('content')
<section class="pt-12 pb-2 flex flex-wrap items-end justify-between gap-4">
    <div>
        <p class="eyebrow">Interno · posamezni odgovor</p>
        <h1 class="text-3xl sm:text-4xl font-extrabold mt-2">{{ $response->municipality?->name ?? '—' }}</h1>
        <p class="text-lg text-[color:var(--color-muted)] mt-3">
            {{ $response->municipality?->region?->name ?? '—' }} ·
            {{ optional($response->submitted_at)->format('d.m.Y H:i') }}
        </p>
    </div>
    <a href="{{ route('results.index', $filter->params()) }}" class="btn">← Nazaj na rezultate</a>
</section>

{{-- prev/next nav --}}
<div class="flex items-center justify-between gap-4 my-6">
    @if($prevId)
        <a href="{{ route('results.response', array_merge(['response' => $prevId], $filter->params())) }}" class="btn">← Starejši</a>
    @else
        <span class="btn opacity-40 pointer-events-none">← Starejši</span>
    @endif

    <span class="text-sm text-[color:var(--color-muted)]">{{ $position }} / {{ $total }}</span>

    @if($nextId)
        <a href="{{ route('results.response', array_merge(['response' => $nextId], $filter->params())) }}" class="btn">Novejši →</a>
    @else
        <span class="btn opacity-40 pointer-events-none">Novejši →</span>
    @endif
</div>

<div class="panel p-6">
    @foreach($survey->questions as $q)
        @if($q->isSection())
            <div class="mt-7 first:mt-0 pb-1.5 border-b-2" style="border-color:var(--color-accent)">
                <h3 class="text-lg font-extrabold uppercase tracking-wide m-0" style="color:var(--color-accent)">{{ $q->label }}</h3>
            </div>
        @else
            @php $answer = $byKey->get($q->key); @endphp
            <div class="py-4 border-b border-[color:var(--color-line)] last:border-b-0">
                <div class="font-bold">{{ $q->label }}</div>
                <div class="mt-1 text-[color:var(--color-accent-2)]">
                    @if($answer)
                        {{ AnswerFormatter::format($q, $answer->value) }}
                    @else
                        <span class="text-[color:var(--color-muted)]">—</span>
                    @endif
                </div>
            </div>
        @endif
    @endforeach
</div>
@endsection
```

- [ ] **Step 2: Verify the view compiles**

Run: `php artisan view:clear && php -r "echo 'ok';"` then load in browser (Step 3). Blade compile errors surface on first render.

- [ ] **Step 3: Verify in browser (logged in)**

Start server: `php artisan serve --port=8899` (background).
In an authenticated browser session, open `/rezultati`, click a response row (added in Task 6), confirm:
- Every question label shows with the respondent's answer (or `—`).
- Choice answers show labels (not raw values); multi-select shows `;`-joined; boolean shows Da/Ne.
- Prev/Next move between responses; "N / M" counter updates; arrows disable at first/last.
Expected: HTTP 200, no errors in `storage/logs/laravel.log`.

---

## Task 6: Dashboard dropdowns + response links

**Files:**
- Modify: `resources/views/results/index.blade.php`

- [ ] **Step 1: Add the filter form in the header**

In `resources/views/results/index.blade.php`, find the header actions block:
```blade
    <div class="flex flex-wrap items-center gap-3">
        <span class="text-sm text-[color:var(--color-muted)]">{{ auth()->user()->name ?? auth()->user()->email }}</span>
        <a href="{{ route('results.export') }}" class="btn btn-primary">⭳ Izvozi CSV</a>
```
Replace the export link line with one that preserves the filter:
```blade
        <a href="{{ route('results.export', $filter->params()) }}" class="btn btn-primary">⭳ Izvozi CSV</a>
```

- [ ] **Step 2: Add the dropdown form below the header section**

Immediately after the closing `</section>` of the header (before the stats `<div class="flex flex-wrap gap-4 my-7">`), insert:
```blade
<form method="GET" action="{{ route('results.index') }}" class="panel p-4 mt-6 flex flex-wrap items-end gap-4" id="results-filter">
    <label class="text-sm">
        <span class="block text-[color:var(--color-muted)] mb-1">Regija</span>
        <select name="region" class="field" id="filter-region" onchange="this.form.querySelector('[name=obcina]').value=''; this.form.submit()">
            <option value="">— vse regije —</option>
            @foreach($regions as $r)
                <option value="{{ $r->slug }}" @selected($filter->region?->slug === $r->slug)>{{ $r->name }}</option>
            @endforeach
        </select>
    </label>

    <label class="text-sm">
        <span class="block text-[color:var(--color-muted)] mb-1">Občina</span>
        <select name="obcina" class="field" id="filter-obcina" onchange="this.form.submit()">
            <option value="">— vse občine —</option>
            @foreach($regions as $r)
                @foreach($r->municipalities as $m)
                    <option value="{{ $m->slug }}"
                        data-region="{{ $r->slug }}"
                        @selected($filter->municipality?->slug === $m->slug)
                        @if($filter->region && $filter->region->slug !== $r->slug) hidden @endif
                    >{{ $m->name }}</option>
                @endforeach
            @endforeach
        </select>
    </label>

    @if($filter->active())
        <a href="{{ route('results.index') }}" class="btn">Počisti filter</a>
    @endif
    <noscript><button type="submit" class="btn btn-primary">Filtriraj</button></noscript>
</form>

<script>
// Narrow the municipality dropdown to the chosen region (client-side nicety; server also enforces).
(function () {
    var reg = document.getElementById('filter-region');
    var obc = document.getElementById('filter-obcina');
    if (!reg || !obc) return;
    function sync() {
        var sel = reg.value;
        Array.prototype.forEach.call(obc.options, function (o) {
            if (!o.value) return; // keep the "all" option
            o.hidden = sel && o.getAttribute('data-region') !== sel;
        });
    }
    reg.addEventListener('change', sync);
    sync();
})();
</script>
```

- [ ] **Step 3: Link each recent-response row to its detail page**

Find the recent-responses table body:
```blade
            @foreach($recent as $r)
                <tr>
                    <td class="whitespace-nowrap">{{ optional($r->submitted_at)->format('d.m.Y H:i') }}</td>
                    <td>{{ $r->municipality?->name ?? '—' }}</td>
                    <td>{{ $r->municipality?->region?->name ?? '—' }}</td>
                </tr>
            @endforeach
```
Replace with (wrap the time cell in a link carrying the filter):
```blade
            @foreach($recent as $r)
                <tr>
                    <td class="whitespace-nowrap">
                        <a href="{{ route('results.response', array_merge(['response' => $r->id], $filter->params())) }}"
                           style="color:var(--color-accent)">
                            {{ optional($r->submitted_at)->format('d.m.Y H:i') }}
                        </a>
                    </td>
                    <td>{{ $r->municipality?->name ?? '—' }}</td>
                    <td>{{ $r->municipality?->region?->name ?? '—' }}</td>
                </tr>
            @endforeach
```

- [ ] **Step 4: Verify in browser (logged in)**

With server on :8899 and an authenticated session:
- Open `/rezultati` — region + municipality dropdowns appear; picking a region narrows the municipality list and reloads scoped; picking a municipality scopes to it.
- The stats, "Po regijah", per-question aggregates, and "Zadnji odgovori" all reflect the filter.
- "Počisti filter" resets; "Izvozi CSV" downloads a file whose contents match the current filter and whose name includes the area slug.
- Clicking a response time opens the per-response page (Task 5) with the filter preserved in prev/next.
Expected: HTTP 200 throughout, no errors in `storage/logs/laravel.log`.

- [ ] **Step 5: Final smoke check of the whole flow**

```bash
php artisan route:list | grep rezultati
```
Expected: three routes — `results.index`, `results.export`, `results.response`.
Then manually confirm unauthenticated access to `/rezultati` redirects to login (302), not a 500.

---

## Self-review notes (addressed)

- **Spec coverage:** geo filter on all aggregates (Task 3), CSV respects filter incl. filename (Task 3 Step 5), dropdowns with region→municipality narrowing (Task 6), per-response view with prev/next within filter (Tasks 4–5), admin-only via existing `auth` group (route placement), shared answer formatting (Task 1). No public view, no drill-down, no tests — matches non-goals.
- **Naming consistency:** `ResultsFilter::apply/params/filenameSuffix/active`, `AnswerFormatter::format`, `results.response` route, view vars (`filter`, `regions`, `byKey`, `prevId`, `nextId`, `position`, `total`) are used identically across controller and views.
- **No placeholders:** every code step shows full code.