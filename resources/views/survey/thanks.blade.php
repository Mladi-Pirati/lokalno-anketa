@extends('layouts.app')
@section('title', 'Hvala')

@section('content')
<section class="pt-16 pb-6">
    <p class="eyebrow">Oddano</p>
    <h1 class="text-4xl sm:text-5xl font-extrabold mt-2">Hvala! <span style="color:var(--color-accent)">Tvoj glas šteje.</span></h1>
    <p class="text-lg text-[color:var(--color-muted)] max-w-2xl mt-4">
        {{ $survey?->thank_you ?? 'Hvala za sodelovanje.' }}
        @if($response->municipality)<br><br>Odgovori za občino <strong class="text-[color:var(--color-fg)]">{{ $response->municipality->name }}</strong> so shranjeni.@endif
    </p>
    <div class="flex flex-wrap gap-3.5 items-center mt-7">
        <a href="{{ route('home') }}" class="btn btn-primary">Nazaj na začetek</a>
    </div>
</section>
@endsection
