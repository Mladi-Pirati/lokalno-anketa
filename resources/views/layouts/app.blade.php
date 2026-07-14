<!DOCTYPE html>
<html lang="sl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#050506">
    <title>@yield('title', ($survey ?? null)?->title ?? 'Lokalna anketa')</title>
    <meta name="description" content="@yield('meta', 'Povej nam, kaj te skrbi v tvoji občini. Anonimna lokalna anketa.')">
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="antialiased">
<main class="mx-auto max-w-6xl px-5">
    @yield('content')
</main>

@stack('scripts')
</body>
</html>
