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