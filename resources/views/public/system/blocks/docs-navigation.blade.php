<aside id="rw-docs-navigation" class="rw-docs-sidebar" aria-label="{{ public_text('docs.navigation.label', 'Documentation contents', $site['current_locale'] ?? null) }}">
    <label for="rw-docs-version" class="rw-public-sr-only">{{ public_text('docs.version.label', 'Version', $site['current_locale'] ?? null) }}</label>
    <select id="rw-docs-version" class="rw-docs-version-select" onchange="if (this.value) window.location.href = this.value">
        @foreach (($versions ?? []) as $versionOption)
            <option value="{{ $versionOption['url'] }}" @selected(! empty($versionOption['active']))>{{ $versionOption['label'] }}</option>
        @endforeach
    </select>

    <nav class="rw-docs-navigation-tree rw-public-prose">
        @include('public.system.docs.partials.navigation-tree', ['items' => $docNavigation ?? [], 'currentPath' => $docPage?->path])
    </nav>
</aside>
