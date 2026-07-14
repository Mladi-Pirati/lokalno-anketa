@extends('layouts.app')
@section('title', $survey->title.' · '.$municipality->name)

@section('content')
<section class="pt-8 pb-2 max-w-2xl mx-auto">
    <a href="{{ route('home') }}" class="back-btn mb-6">← Nazaj na zemljevid</a>
    <p class="eyebrow">{{ $municipality->region?->name }}</p>
    <h1 class="text-4xl sm:text-5xl font-extrabold mt-2">{{ $municipality->name }}</h1>
    <span class="inline-flex items-center gap-2 mt-4 rounded-full px-3.5 py-1.5 text-sm font-semibold panel">
        <span class="w-2 h-2 rounded-full" style="background:var(--color-accent)"></span> {{ $survey->title }}
    </span>
    @if($survey->intro)<p class="text-lg text-[color:var(--color-muted)] max-w-2xl mt-4">{{ $survey->intro }}</p>@endif
</section>

<div class="panel p-6 sm:p-8 max-w-2xl mx-auto mt-4">
    @if($errors->any())
        <div class="rounded-xl px-4 py-3 mb-5 text-sm" style="background:#2a1414;border:1px solid #ff6b6b;color:#ff9b9b">
            Preveri označena polja spodaj.
        </div>
    @endif

    <form method="POST" action="{{ route('survey.store', $municipality->slug) }}" novalidate>
        @csrf
        @foreach($survey->questions as $question)
            @include('survey.partials.question', ['question' => $question])
        @endforeach

        <div class="flex flex-wrap gap-3.5 items-center mt-7">
            <button type="submit" class="btn btn-primary">Pošlji odgovore →</button>
            <a href="{{ route('home') }}" class="btn btn-ghost">Zamenjaj občino</a>
        </div>
        <p class="text-sm text-[color:var(--color-muted)] mt-4">Anketa je anonimna. Ne shranjujemo tvojega imena; IP naslov shranimo le v šifrirani obliki.</p>
    </form>
</div>
@endsection
