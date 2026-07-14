@extends('layouts.app')
@section('title', 'Izberi svojo občino')

@section('content')
    <section class="pt-8 pb-4">
        <p class="eyebrow">Lokalna anketa</p>
        <h1 class="text-3xl sm:text-4xl font-extrabold mt-2">Tvoja občina, <span style="color:var(--color-accent)">tvoj glas.</span>
        </h1>
        <p class="text-base sm:text-lg text-[color:var(--color-muted)] max-w-2xl mt-3">
            @if($survey)
                {{ $survey->description }}
            @else
                Trenutno ni aktivne ankete.
            @endif
        </p>
    </section>

    @if($survey)
        <div class="map-wide">
            <div class="mb-3 flex items-center gap-3 flex-wrap">
                <button type="button" id="map-back" class="back-btn hidden">← Vse regije</button>
                <h2 id="map-title" class="text-lg sm:text-xl font-extrabold m-0">1. korak — izberi regijo</h2>
            </div>

            <div id="map-stage" class="map-stage" data-mode="regions"
                 data-has-geometry="{{ $hasGeometry ? '1' : '0' }}">
                <div id="map-empty" class="p-10 text-center text-sm text-[color:var(--color-muted)]">
                    Zemljevid ni na voljo.
                </div>
                <div id="map-svg" class="hidden"></div>
            </div>

            {{-- how it works --}}
            <div class="mt-8 grid gap-4 sm:grid-cols-3">
                @foreach ([
                    ['1', 'Izberi regijo', 'Klikni regijo na zemljevidu.'],
                    ['2', 'Izberi občino', 'Približaj in klikni svojo občino.'],
                    ['3', 'Izpolni anketo', 'Kratko, anonimno, približno 5 minut.'],
                ] as [$n, $t, $d])
                    <div class="panel p-5 flex items-start gap-4">
                <span class="shrink-0 w-9 h-9 rounded-full grid place-items-center font-extrabold"
                      style="background:var(--color-accent);color:var(--color-accent-ink)">{{ $n }}</span>
                        <div>
                            <div class="font-bold">{{ $t }}</div>
                            <div class="text-sm text-[color:var(--color-muted)] mt-0.5">{{ $d }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- no-JS fallback: full list of regions + municipalities --}}
        <noscript>
            <div class="mt-6 grid gap-3">
                @foreach($regions as $region)
                    <details class="panel p-4">
                        <summary class="cursor-pointer font-bold">{{ $region->name }}
                            ({{ $region->municipalities->count() }})
                        </summary>
                        <div class="flex flex-wrap gap-2 mt-3">
                            @foreach($region->municipalities as $m)
                                <a class="chip" href="{{ route('survey.show', $m->slug) }}">{{ $m->name }}</a>
                            @endforeach
                        </div>
                    </details>
                @endforeach
            </div>
        </noscript>

        <script type="application/json" id="picker-config">
            {!! json_encode(['muniBase' => url('/obcina')], JSON_UNESCAPED_SLASHES) !!}
        </script>
    @endif
@endsection

@push('scripts')
    <script src="{{ asset('js/map.js') }}"></script>
@endpush
