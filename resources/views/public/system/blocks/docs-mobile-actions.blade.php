<nav class="rw-docs-mobile-actions" aria-label="{{ public_text('docs.mobile.actions_label', 'Documentation shortcuts', $site['current_locale'] ?? null) }}">
    <details class="rw-docs-drawer rw-docs-drawer--left">
        <summary class="rw-docs-drawer__toggle rw-docs-drawer__toggle--left">
            <span class="mdi mdi-menu-open" aria-hidden="true"></span>
            <span>{{ public_text('docs.mobile.navigation', 'Contents', $site['current_locale'] ?? null) }}</span>
        </summary>
        <div class="rw-docs-drawer__panel" role="dialog" aria-label="{{ public_text('docs.navigation.label', 'Documentation contents', $site['current_locale'] ?? null) }}">
            <div class="rw-docs-drawer__header">
                <span>{{ public_text('docs.mobile.navigation', 'Contents', $site['current_locale'] ?? null) }}</span>
                <button
                    type="button"
                    class="rw-docs-drawer__close"
                    title="{{ public_text('docs.mobile.close_navigation', 'Close contents', $site['current_locale'] ?? null) }}"
                    aria-label="{{ public_text('docs.mobile.close_navigation', 'Close contents', $site['current_locale'] ?? null) }}"
                    data-rw-docs-drawer-close
                >
                    <span class="mdi mdi-close" aria-hidden="true"></span>
                </button>
            </div>
            <label for="rw-docs-mobile-version" class="rw-public-sr-only">{{ public_text('docs.version.label', 'Version', $site['current_locale'] ?? null) }}</label>
            <select id="rw-docs-mobile-version" class="rw-docs-version-select" onchange="if (this.value) window.location.href = this.value">
                @foreach (($versions ?? []) as $versionOption)
                    <option value="{{ $versionOption['url'] }}" @selected(! empty($versionOption['active']))>{{ $versionOption['label'] }}</option>
                @endforeach
            </select>
            <nav class="rw-docs-navigation-tree rw-public-prose">
                @include('public.system.docs.partials.navigation-tree', ['items' => $docNavigation ?? [], 'currentPath' => $docPage?->path])
            </nav>
        </div>
    </details>

    <details class="rw-docs-drawer rw-docs-drawer--right">
        <summary class="rw-docs-drawer__toggle rw-docs-drawer__toggle--right">
            <span>{{ public_text('docs.mobile.page_toc', 'On this page', $site['current_locale'] ?? null) }}</span>
            <span class="mdi mdi-format-list-bulleted" aria-hidden="true"></span>
        </summary>
        <div class="rw-docs-drawer__panel" role="dialog" aria-label="{{ public_text('docs.page_toc.label', 'On this page', $site['current_locale'] ?? null) }}">
            <div class="rw-docs-drawer__header">
                <span>{{ public_text('docs.page_toc.title', 'On this page', $site['current_locale'] ?? null) }}</span>
                <button
                    type="button"
                    class="rw-docs-drawer__close"
                    title="{{ public_text('docs.mobile.close_page_toc', 'Close page navigation', $site['current_locale'] ?? null) }}"
                    aria-label="{{ public_text('docs.mobile.close_page_toc', 'Close page navigation', $site['current_locale'] ?? null) }}"
                    data-rw-docs-drawer-close
                >
                    <span class="mdi mdi-close" aria-hidden="true"></span>
                </button>
            </div>
            <div class="rw-public-prose">
                <ul class="rw-docs-nav-list">
                    @forelse (($rendered['toc'] ?? []) as $tocItem)
                        <li style="margin-left: {{ max(0, ((int) $tocItem['level']) - 2) }}rem;">
                            <a class="rw-docs-toc-link" href="#{{ $tocItem['id'] }}">{{ $tocItem['title'] }}</a>
                        </li>
                    @empty
                        <li>{{ public_text('docs.page_toc.empty', 'No sections on this page.', $site['current_locale'] ?? null) }}</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </details>
</nav>
