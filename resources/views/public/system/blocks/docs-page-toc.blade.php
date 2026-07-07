<aside id="rw-docs-page-toc" class="rw-docs-toc" aria-label="{{ public_text('docs.page_toc.label', 'On this page', $site['current_locale'] ?? null) }}">
    <div class="rw-docs-toc__header">
        <h2>{{ public_text('docs.page_toc.title', 'On this page', $site['current_locale'] ?? null) }}</h2>
        <button
            type="button"
            class="rw-docs-toc__toggle"
            title="{{ public_text('docs.page_toc.hide', 'Hide page navigation', $site['current_locale'] ?? null) }}"
            aria-label="{{ public_text('docs.page_toc.hide', 'Hide page navigation', $site['current_locale'] ?? null) }}"
            aria-controls="rw-docs-page-toc"
            aria-expanded="true"
            data-rw-docs-toc-toggle
        >
            <span class="mdi mdi-chevron-right" aria-hidden="true"></span>
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
</aside>
