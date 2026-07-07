<ul class="rw-docs-nav-list">
    @foreach ($items as $item)
        <li>
            <a class="rw-docs-nav-link" href="{{ $item['url'] }}" @if (($currentPath ?? null) === $item['path']) aria-current="page" @endif>
                {{ $item['title'] }}
            </a>

            @if (! empty($item['children']))
                @include('public.system.docs.partials.navigation-tree', ['items' => $item['children'], 'currentPath' => $currentPath ?? null])
            @endif
        </li>
    @endforeach
</ul>
