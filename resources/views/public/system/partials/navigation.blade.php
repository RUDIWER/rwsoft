@php
    $isChild = (bool) ($is_child ?? false);
    $navIdPrefix = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($nav_id_prefix ?? 'navigation'));
    $currentPath = $currentPath ?? request()->getPathInfo();
    $isCurrentNavigationItem = $isCurrentNavigationItem ?? function (array $item) use ($currentPath): bool {
        $url = trim((string) ($item['url'] ?? ''));

        if ($url === '' || preg_match('/^(?:mailto|tel):/i', $url) === 1) {
            return false;
        }

        $path = parse_url($url, PHP_URL_PATH);
        $path = is_string($path) && $path !== '' ? $path : '/';

        return rtrim($path, '/') === rtrim($currentPath, '/') || ($path === '/' && $currentPath === '/');
    };
    $isActiveNavigationItem = $isActiveNavigationItem ?? function (array $item) use (&$isActiveNavigationItem, $isCurrentNavigationItem): bool {
        if ($isCurrentNavigationItem($item)) {
            return true;
        }

        foreach ((array) ($item['children'] ?? []) as $child) {
            if (is_array($child) && $isActiveNavigationItem($child)) {
                return true;
            }
        }

        return false;
    };
@endphp

@if (! empty($items))
    @if ($isChild)
        <div
            @if (! empty($children_id)) id="{{ $children_id }}" @endif
            class="rw-public-nav__children"
            role="group"
            data-rw-public-submenu-panel
        >
    @else
        <nav class="rw-public-nav" aria-label="{{ $label }}">
    @endif
        @foreach ($items as $item)
            @php
                $isCurrentItem = $isCurrentNavigationItem((array) $item);
                $isActiveItem = $isActiveNavigationItem((array) $item);
                $children = array_values(array_filter((array) ($item['children'] ?? []), 'is_array'));
                $childrenId = $navIdPrefix.'-children-'.$loop->index;
            @endphp
            <div class="rw-public-nav__item {{ $children !== [] ? 'has-children' : '' }} {{ $isActiveItem ? 'is-active' : '' }}">
                <a
                    class="rw-public-nav__link {{ $isActiveItem ? 'is-active' : '' }}"
                    href="{{ $item['url'] }}"
                    @if ($isCurrentItem) aria-current="page" @endif
                    @if ($children !== []) aria-expanded="false" aria-controls="{{ $childrenId }}" data-rw-public-submenu-toggle @endif
                    @if (! empty($item['target'])) target="{{ $item['target'] }}" @endif
                    @if (! empty($item['rel'])) rel="{{ $item['rel'] }}" @elseif (($item['target'] ?? null) === '_blank') rel="noopener noreferrer" @endif
                >{{ $item['label'] }}</a>

                @if ($children !== [])
                    @include('public.system.partials.navigation', [
                        'items' => $children,
                        'label' => $label,
                        'title' => $title ?? null,
                        'is_child' => true,
                        'nav_id_prefix' => $childrenId,
                        'children_id' => $childrenId,
                        'currentPath' => $currentPath,
                        'isCurrentNavigationItem' => $isCurrentNavigationItem,
                        'isActiveNavigationItem' => $isActiveNavigationItem,
                    ])
                @endif
            </div>
        @endforeach

    @if ($isChild)
        </div>
    @else
        </nav>
    @endif
@endif
