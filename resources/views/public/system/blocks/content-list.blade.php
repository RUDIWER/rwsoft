<section class="rw-public-block rw-public-content-list rw-public-content-list--{{ $block['layout'] ?? 'grid' }}">
    @if (! empty($block['title']))
        <h2 class="rw-public-block__title">{{ $block['title'] }}</h2>
    @endif

    @if (! empty($block['show_search']))
        <form class="rw-public-content-list__search" method="get">
            <label class="rw-public-content-list__search-label" for="content-list-search-{{ $contentItem['id'] ?? 'page' }}">
                {{ public_text('content_list.search_label', 'Search', $site['current_locale'] ?? null) }}
            </label>
            <div class="rw-public-content-list__search-controls">
                <input
                    id="content-list-search-{{ $contentItem['id'] ?? 'page' }}"
                    class="rw-public-content-list__search-input"
                    type="search"
                    name="q"
                    value="{{ $block['search_query'] ?? '' }}"
                    placeholder="{{ public_text('content_list.search_placeholder', 'Search this list', $site['current_locale'] ?? null) }}"
                >
                <button class="rw-public-button rw-public-button--sm" type="submit">{{ public_text('content_list.search_button', 'Search', $site['current_locale'] ?? null) }}</button>
                @if (! empty($block['search_query']))
                    <a class="rw-public-content-list__search-reset" href="{{ request()->url() }}">{{ public_text('content_list.search_reset', 'Clear', $site['current_locale'] ?? null) }}</a>
                @endif
            </div>
        </form>
    @endif

    @if (! empty($block['items']))
        @if (($block['layout'] ?? 'grid') === 'rows')
            <div class="rw-public-content-list__rows" role="list">
                @foreach ($block['items'] as $item)
                    <a class="rw-public-content-list__row" href="{{ $item['url'] }}" role="listitem">
                        <span class="rw-public-content-list__row-title">{{ $item['title'] }}</span>
                        @if (! empty($block['show_date']) && ! empty($item['published_at']))
                            <span class="rw-public-post-meta">{{ $item['published_at'] }}</span>
                        @endif
                        @if (! empty($block['show_excerpt']) && ! empty($item['excerpt']))
                            <span class="rw-public-content-list__row-excerpt">{{ $item['excerpt'] }}</span>
                        @endif
                    </a>
                @endforeach
            </div>
        @else
            <div class="rw-public-post-grid">
                @foreach ($block['items'] as $item)
                    <a class="rw-public-card rw-public-post-card" href="{{ $item['url'] }}">
                        @if (! empty($block['show_image']) && ! empty($item['featured_media']))
                            <img
                                class="rw-public-image"
                                src="{{ $item['featured_media']['url'] }}"
                                alt="{{ $item['featured_media']['alt_text'] ?: $item['title'] }}"
                                loading="lazy"
                            >
                        @endif
                        <div class="rw-public-post-card__body">
                            @if (! empty($block['show_categories']) && ! empty($item['taxonomy_items'] ?? $item['categories'] ?? []))
                                <div class="rw-public-chip-list">
                                    @foreach (($item['taxonomy_items'] ?? $item['categories']) as $taxonomyItem)
                                        <span class="rw-public-chip">{{ $item['taxonomy_prefix'] ?? '' }}{{ $taxonomyItem['title'] }}</span>
                                    @endforeach
                                </div>
                            @endif
                            @if (! empty($block['show_date']) && ! empty($item['published_at']))
                                <div class="rw-public-post-meta">{{ $item['published_at'] }}</div>
                            @endif
                            <h2 class="rw-public-post-card__title">{{ $item['title'] }}</h2>
                            @if (! empty($block['show_excerpt']) && ! empty($item['excerpt']))
                                <p class="rw-public-post-card__excerpt">{{ $item['excerpt'] }}</p>
                            @endif
                            <span class="rw-public-button">{{ public_text('post_index.read_more', 'Lees meer', $site['current_locale'] ?? null) }}</span>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    @else
        <p class="rw-public-block__text">{{ $block['empty_text'] ?? public_text('content_list.empty', 'There are no results yet.', $site['current_locale'] ?? null) }}</p>
    @endif

    @if (! empty($block['slots']['actions']))
        @include('public.system.partials.block-slot', [
            'slot' => $block['slots']['actions'],
            'section' => $section ?? [],
            'contentItem' => $contentItem ?? null,
        ])
    @endif
</section>
