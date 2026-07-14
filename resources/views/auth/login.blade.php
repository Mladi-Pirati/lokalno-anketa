@extends('layouts.app')
@section('title', 'Prijava')

@section('content')
<section class="min-h-screen flex items-center justify-center py-16">
    <div class="panel p-8 w-full max-w-md text-center">
        <p class="eyebrow">Interno</p>
        <h1 class="text-2xl font-extrabold mt-2">Prijava</h1>
        <p class="text-[color:var(--color-muted)] mt-3">Za dostop do rezultatov se prijavi s Keycloak.</p>

        @if(session('error'))
            <div class="rounded-xl px-4 py-3.5 text-sm mt-5" style="background:#241010;border:1px solid #a33;color:#f2b8b8">
                {{ session('error') }}
            </div>
        @endif

        <a href="{{ route('auth.redirect') }}" class="btn btn-primary w-full mt-6 justify-center">
            Prijava s Keycloak
        </a>
    </div>
</section>
@endsection