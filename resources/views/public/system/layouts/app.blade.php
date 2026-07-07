<!doctype html>
<html lang="{{ $site['current_locale'] ?? $site['default_locale'] ?? app()->getLocale() }}">
    <head>
        @include('public.system.partials.head')
    </head>
    <body class="rw-public">
        <div class="rw-public-shell">
            @include('public.system.partials.header')

            <main class="rw-public-main rw-public-container">
                @include('public.system.partials.flash')
                @yield('content')
            </main>
        </div>

        @livewireScripts
    </body>
</html>
