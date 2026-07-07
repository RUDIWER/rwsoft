@php
    $localeCode = $site['current_locale'] ?? $site['default_locale'] ?? app()->getLocale();
    $block = is_array($block ?? null) ? $block : ['renderer_key' => ''];
    $menuKey = $block['menu_key'] ?? 'header';
    $menuData = is_array($navigation[$menuKey] ?? null) ? $navigation[$menuKey] : ($navigation['header'] ?? ['title' => null, 'items' => []]);
    $normalizeMenuItems = function (array $items) use (&$normalizeMenuItems): array {
        return collect($items)
            ->filter(fn (mixed $item): bool => is_array($item))
            ->map(function (array $item) use (&$normalizeMenuItems): array {
                $target = in_array($item['target'] ?? null, ['_self', '_blank'], true) ? (string) $item['target'] : '_self';
                $rel = trim((string) ($item['rel'] ?? ''));

                return [
                    'id' => (int) ($item['id'] ?? 0),
                    'label' => (string) ($item['label'] ?? ''),
                    'url' => (string) ($item['url'] ?? '#'),
                    'target' => $target,
                    'rel' => $rel !== '' ? $rel : ($target === '_blank' ? 'noopener noreferrer' : ''),
                    'children' => $normalizeMenuItems(is_array($item['children'] ?? null) ? $item['children'] : []),
                ];
            })
            ->values()
            ->all();
    };
    $menuData = [
        'title' => (string) ($menuData['title'] ?? ''),
        'label' => (string) ($menuData['title'] ?? public_text('navigation.'.$menuKey.'_label', ucfirst((string) $menuKey).' navigation', $localeCode)),
        'mobile_label' => (string) (($block['mobile_label'] ?? '') ?: public_text('mobile_menu.title', 'Menu', $localeCode)),
        'items' => $normalizeMenuItems(is_array($menuData['items'] ?? null) ? $menuData['items'] : []),
    ];
    $block['label'] = (string) (($block['label'] ?? '') ?: public_text('language_switcher.label', 'Taalkeuze', $localeCode));
    $block['alt_text'] = (string) (($block['alt_text'] ?? '') ?: ($site['name'] ?? config('app.name')));
    $block['title'] = (string) (($block['title'] ?? '') ?: ($site['name'] ?? ''));
    $block['link_url'] = (string) (($block['link_url'] ?? '') ?: '/');
    $placeableBlockTemplate = is_array($block['placeable_block'] ?? null) ? trim((string) ($block['placeable_block']['template_source'] ?? '')) : '';
    $template = $placeableBlockTemplate !== ''
        ? $placeableBlockTemplate
        : null;
    $renderedSlots = collect(is_array($block['slots'] ?? null) ? $block['slots'] : [])
        ->filter(fn (mixed $slot): bool => is_array($slot))
        ->map(function (array $slot) use ($section, $contentItem): array {
            $slot['html'] = view('public.system.partials.block-slot', [
                'slot' => $slot,
                'section' => $section ?? [],
                'contentItem' => $contentItem ?? null,
            ])->render();

            return $slot;
        })
        ->all();
    $html = $template === null
        ? ''
        : app(App\Support\Cms\SafeBladeRenderer::class)->render($template, [
            'block' => $block,
            'contentItem' => $contentItem ?? null,
            'site' => $site ?? [],
            'locale' => [
                'current' => $localeCode,
                'default' => $site['default_locale'] ?? config('app.locale'),
                'available' => collect($translations ?? [])->map(fn (array $translation): array => [
                    'locale' => $translation['locale'] ?? null,
                    'label' => strtoupper((string) ($translation['locale'] ?? '')),
                    'url' => $translation['url'] ?? '#',
                    'active' => (bool) ($translation['active'] ?? false),
                ])->values()->all(),
            ],
            'params' => [],
            'user' => [
                'is_authenticated' => auth()->check(),
                'login_url' => Illuminate\Support\Facades\Route::has('login') ? route('login') : '/login',
                'account_url' => Illuminate\Support\Facades\Route::has('dashboard') ? route('dashboard') : '/admin',
            ],
            'route' => [
                'name' => request()->route()?->getName(),
                'path' => request()->path(),
            ],
            'placement' => $placement ?? [],
            'section' => $section ?? [],
            'placeable_block' => is_array($block['placeable_block'] ?? null) ? $block['placeable_block'] : [],
            'menu' => $menuData,
            'slots' => $renderedSlots,
        ]);
@endphp

{!! $html !!}
