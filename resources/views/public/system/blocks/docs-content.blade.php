<article class="rw-docs-content rw-public-prose">
    <button
        type="button"
        class="rw-docs-toc-restore"
        title="{{ public_text('docs.page_toc.show', 'Show page navigation', $site['current_locale'] ?? null) }}"
        aria-label="{{ public_text('docs.page_toc.show', 'Show page navigation', $site['current_locale'] ?? null) }}"
        aria-controls="rw-docs-page-toc"
        aria-expanded="false"
        data-rw-docs-toc-restore
    >
        <span class="mdi mdi-format-list-bulleted" aria-hidden="true"></span>
    </button>

    @if ($docPage ?? null)
        <h1>{{ $docPage->title }}</h1>
        {!! $rendered['html'] ?? '' !!}
    @else
        <h1>{{ $collection?->name }}</h1>
        <p>{{ public_text('docs.empty_version', 'No published documentation pages are available for this version yet.', $site['current_locale'] ?? null) }}</p>
    @endif
</article>
