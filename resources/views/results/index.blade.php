@extends('layouts.app')
@section('title', 'Rezultati')

@section('content')
<section class="pt-12 pb-2 flex flex-wrap items-end justify-between gap-4">
    <div>
        <p class="eyebrow">Interno</p>
        <h1 class="text-3xl sm:text-4xl font-extrabold mt-2">Rezultati ankete</h1>
        <p class="text-lg text-[color:var(--color-muted)] mt-3">{{ $survey->title }}</p>
    </div>
    <div class="flex flex-wrap items-center gap-3">
        <span class="text-sm text-[color:var(--color-muted)]">{{ auth()->user()->name ?? auth()->user()->email }}</span>
        <a href="{{ route('results.export', $filter->params()) }}" class="btn btn-primary">⭳ Izvozi CSV</a>
        <form method="POST" action="{{ route('auth.logout') }}">
            @csrf
            <button type="submit" class="btn">Odjava</button>
        </form>
    </div>
</section>

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

<div class="flex flex-wrap gap-4 my-7">
    <div class="panel px-6 py-5 flex-1 min-w-36">
        <div class="text-4xl font-extrabold" style="color:var(--color-accent)">{{ $total }}</div>
        <div class="text-xs uppercase tracking-wider text-[color:var(--color-muted)]">Vseh odgovorov</div>
    </div>
    <div class="panel px-6 py-5 flex-1 min-w-36">
        <div class="text-4xl font-extrabold" style="color:var(--color-accent)">{{ $byRegion->count() }}</div>
        <div class="text-xs uppercase tracking-wider text-[color:var(--color-muted)]">Aktivnih regij</div>
    </div>
    <div class="panel px-6 py-5 flex-1 min-w-36">
        <div class="text-4xl font-extrabold" style="color:var(--color-accent)">{{ $recent->pluck('municipality_id')->unique()->count() }}</div>
        <div class="text-xs uppercase tracking-wider text-[color:var(--color-muted)]">Občin (zadnjih 50)</div>
    </div>
</div>

@if($total === 0)
    <div class="rounded-xl px-4 py-3.5 text-sm" style="background:#241a10;border:1px solid var(--color-accent);color:var(--color-accent-2)">Še ni odgovorov.</div>
@else
<div class="grid gap-7 md:grid-cols-2">
    <div class="panel p-6">
        <h2 class="text-xl font-extrabold mb-4">Po regijah</h2>
        @php $maxR = $byRegion->max() ?: 1; @endphp
        @foreach($byRegion as $name => $c)
            <div class="mb-4">
                <div class="flex justify-between text-sm mb-1.5"><strong>{{ $name }}</strong><span>{{ $c }}</span></div>
                <div class="bar"><i style="width:{{ round($c / $maxR * 100) }}%"></i></div>
            </div>
        @endforeach
    </div>

    <div class="panel p-6">
        <h2 class="text-xl font-extrabold mb-4">Ocene (povprečja 1–5)</h2>
        @forelse($scales as $s)
            @php $q = $s['question']; $pct = round(($s['avg'] - $s['min']) / max(1, ($s['max'] - $s['min'])) * 100); @endphp
            <div class="mb-4">
                <div class="flex justify-between text-sm mb-1.5 gap-3">
                    <span>{{ $q->label }}</span>
                    <strong class="whitespace-nowrap" style="color:var(--color-accent)">{{ number_format($s['avg'], 2) }}</strong>
                </div>
                <div class="bar"><i style="width:{{ $pct }}%"></i></div>
                <div class="text-xs text-[color:var(--color-muted)] mt-1">{{ $s['n'] }} odgovorov</div>
            </div>
        @empty
            <div class="text-sm text-[color:var(--color-muted)]">Ni ocenjevalnih vprašanj.</div>
        @endforelse
    </div>
</div>

<div class="panel p-6 mt-7">
    <h2 class="text-xl font-extrabold mb-4">Odgovori po vprašanjih</h2>
    <div class="grid gap-7 md:grid-cols-2">
        @foreach($aggregates as $agg)
            @php $q = $agg['question']; $counts = $agg['counts']; $sum = array_sum($counts) ?: 1; @endphp
            <div class="mb-2">
                <strong>{{ $q->label }}</strong>
                @forelse($counts as $label => $c)
                    <div class="flex justify-between text-sm mt-3 mb-1.5 gap-3"><span>{{ $label }}</span><span class="whitespace-nowrap">{{ $c }} · {{ round($c/$sum*100) }}%</span></div>
                    <div class="bar"><i style="width:{{ round($c/$sum*100) }}%"></i></div>
                @empty
                    <div class="text-sm text-[color:var(--color-muted)] mt-2">Ni odgovorov</div>
                @endforelse
            </div>
        @endforeach
    </div>
</div>

<div class="panel p-6 mt-7">
    <h2 class="text-xl font-extrabold mb-4">Zadnji odgovori</h2>
    <div class="overflow-x-auto">
        <table class="data-table">
            <thead><tr><th>Čas</th><th>Občina</th><th>Regija</th></tr></thead>
            <tbody>
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
            </tbody>
        </table>
    </div>
</div>
@endif
@endsection
