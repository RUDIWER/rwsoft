@php
    $headingLevel = in_array($block['heading_level'] ?? null, ['h1', 'h2', 'h3'], true) ? $block['heading_level'] : null;
    $valueType = $block['value_type'] ?? 'empty';
    $value = $block['value'] ?? null;
    $listUsesCards = $valueType === 'list'
        && is_array($value)
        && collect($value)->contains(fn ($item) => is_array($item) && (! empty($item['url']) || ! empty($item['excerpt']) || ! empty($item['featured_media'])));
@endphp

@if ($valueType !== 'empty')
    <div class="rw-public-block--dynamic-field">
        @if (! empty($block['title']))
            <h2 class="rw-public-block__title">{{ $block['title'] }}</h2>
        @endif

        @if ($valueType === 'media' && is_array($value) && ! empty($value['url']))
            <figure>
                <img
                    class="rw-public-image"
                    src="{{ $value['url'] }}"
                    alt="{{ $value['alt_text'] ?? ($contentItem['title'] ?? '') }}"
                    @if (! empty($value['width'])) width="{{ $value['width'] }}" @endif
                    @if (! empty($value['height'])) height="{{ $value['height'] }}" @endif
                    loading="lazy"
                >
                @if (! empty($value['caption']))
                    <figcaption class="rw-public-image-caption">{{ $value['caption'] }}</figcaption>
                @endif
            </figure>
        @elseif ($listUsesCards)
            <div class="rw-public-post-grid">
                @foreach ($value as $item)
                    @if (is_array($item))
                        <a class="rw-public-card rw-public-post-card" href="{{ $item['url'] ?? '#' }}">
                            @if (! empty($item['featured_media']))
                                <img
                                    class="rw-public-image"
                                    src="{{ $item['featured_media']['url'] }}"
                                    alt="{{ $item['featured_media']['alt_text'] ?: ($item['title'] ?? '') }}"
                                    loading="lazy"
                                >
                            @endif
                            <div class="rw-public-post-card__body">
                                @if (! empty($item['published_at']) && ! empty($item['taxonomy_items'] ?? $item['categories'] ?? []))
                                    <div class="rw-public-chip-list">
                                        @foreach (($item['taxonomy_items'] ?? $item['categories']) as $taxonomyItem)
                                            <span class="rw-public-chip">{{ $item['taxonomy_prefix'] ?? '' }}{{ $taxonomyItem['title'] }}</span>
                                        @endforeach
                                    </div>
                                @endif
                                @if (! empty($item['published_at']))
                                    <div class="rw-public-post-meta">{{ $item['published_at'] }}</div>
                                @endif
                                <h2 class="rw-public-post-card__title">{{ $item['title'] ?? $item['name'] ?? $item['slug'] ?? '' }}</h2>
                                @if (! empty($item['excerpt']))
                                    <p class="rw-public-post-card__excerpt">{{ $item['excerpt'] }}</p>
                                @endif
                                @if (! empty($item['url']))
                                    <span class="rw-public-button">{{ public_text('post_index.read_more', 'Read more', $site['current_locale'] ?? null) }}</span>
                                @endif
                            </div>
                        </a>
                    @endif
                @endforeach
            </div>
        @elseif ($valueType === 'list' && is_array($value))
            <div class="rw-public-chip-list">
                @foreach ($value as $item)
                    @if (is_array($item))
                        @if (! empty($item['url']))
                            <a class="rw-public-chip" href="{{ $item['url'] }}">{{ $item['title'] ?? $item['name'] ?? $item['slug'] ?? '' }}</a>
                        @else
                            <span class="rw-public-chip">{{ $item['title'] ?? $item['name'] ?? $item['slug'] ?? '' }}</span>
                        @endif
                    @else
                        <span class="rw-public-chip">{{ $item }}</span>
                    @endif
                @endforeach
            </div>
        @elseif ($valueType === 'object' && is_array($value))
            <p class="rw-public-block__text">{{ $value['title'] ?? $value['name'] ?? $value['slug'] ?? '' }}</p>
        @else
            @if ($headingLevel === 'h1')
                <h1 class="rw-public-title">{{ $value }}</h1>
            @elseif ($headingLevel === 'h2')
                <h2 class="rw-public-block__title">{{ $value }}</h2>
            @elseif ($headingLevel === 'h3')
                <h3 class="rw-public-block__title">{{ $value }}</h3>
            @else
                <p class="rw-public-block__text">{{ $value }}</p>
            @endif
        @endif
    </div>
@endif
