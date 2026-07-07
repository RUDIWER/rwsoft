<footer class="rw-public-footer">
    <div class="rw-public-footer__inner rw-public-container">
        <div class="rw-public-brand">{{ $site['name'] ?? config('app.name') }}</div>
        @include('public.system.partials.navigation', [
            'items' => $navigation['footer']['items'] ?? [],
            'label' => $navigation['footer']['title'] ?? public_text('navigation.footer_label', 'Footernavigatie', $site['current_locale'] ?? null),
            'title' => $navigation['footer']['title'] ?? null,
        ])
    </div>
</footer>
