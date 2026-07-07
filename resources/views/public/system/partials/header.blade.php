<header class="rw-public-header">
    <div class="rw-public-header__inner rw-public-container">
        <a class="rw-public-brand" href="{{ ! empty($site['multilingual_enabled']) ? '/'.($site['current_locale'] ?? $site['default_locale'] ?? app()->getLocale()) : '/' }}">
            @if (! empty($site['logo_url']))
                <img class="rw-public-brand__logo" src="{{ $site['logo_url'] }}" alt="{{ $site['name'] ?? config('app.name') }}">
            @else
                <span>{{ $site['name'] ?? config('app.name') }}</span>
            @endif
            @if ((! empty($site['logo_show_tagline']) || empty($site['logo_url'])) && ! empty($site['tagline']))
                <span class="rw-public-brand__tagline">{{ $site['tagline'] }}</span>
            @endif
        </a>

        <div class="rw-public-header__nav">
            @include('public.system.partials.navigation', [
                'items' => $navigation['header']['items'] ?? [],
                'label' => $navigation['header']['title'] ?? public_text('navigation.header_label', 'Hoofdnavigatie', $site['current_locale'] ?? null),
                'title' => $navigation['header']['title'] ?? null,
            ])

            @if (! empty($site['multilingual_enabled']) && count($translations ?? []) > 1)
                <nav class="rw-public-languages" aria-label="{{ public_text('language_switcher.label', 'Taalkeuze', $site['current_locale'] ?? null) }}">
                    @foreach ($translations as $translation)
                        <a
                            class="rw-public-languages__link {{ ! empty($translation['active']) ? 'is-active' : '' }}"
                            href="{{ $translation['url'] }}"
                            hreflang="{{ $translation['locale'] }}"
                        >{{ strtoupper($translation['locale']) }}</a>
                    @endforeach
                </nav>
            @endif
        </div>

    </div>
</header>

<details class="rw-public-mobile-menu">
    <summary class="rw-public-mobile-menu__toggle" aria-label="{{ public_text('mobile_menu.toggle_label', 'Menu openen of sluiten', $site['current_locale'] ?? null) }}">
        <svg class="rw-public-mobile-menu__icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <path d="M4 7h16M4 12h16M4 17h16" />
        </svg>
    </summary>
    <div class="rw-public-mobile-menu__panel">
        <div class="rw-public-mobile-menu__header">
            <span>{{ public_text('mobile_menu.title', 'Menu', $site['current_locale'] ?? null) }}</span>
            <span class="rw-public-mobile-menu__hint">{{ public_text('mobile_menu.close_hint', 'Tik opnieuw op het icoon om te sluiten', $site['current_locale'] ?? null) }}</span>
        </div>

        @include('public.system.partials.navigation', [
            'items' => $navigation['header']['items'] ?? [],
            'label' => $navigation['header']['title'] ?? public_text('navigation.mobile_header_label', 'Mobiele hoofdnavigatie', $site['current_locale'] ?? null),
            'title' => $navigation['header']['title'] ?? null,
        ])

        @if (! empty($site['multilingual_enabled']) && count($translations ?? []) > 1)
            <nav class="rw-public-languages" aria-label="{{ public_text('language_switcher.mobile_label', 'Mobiele taalkeuze', $site['current_locale'] ?? null) }}">
                @foreach ($translations as $translation)
                    <a
                        class="rw-public-languages__link {{ ! empty($translation['active']) ? 'is-active' : '' }}"
                        href="{{ $translation['url'] }}"
                        hreflang="{{ $translation['locale'] }}"
                    >{{ strtoupper($translation['locale']) }}</a>
                @endforeach
            </nav>
        @endif
    </div>
</details>
