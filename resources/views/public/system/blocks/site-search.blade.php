@php
    $locale = (string) ($site['current_locale'] ?? app()->getLocale());
    $searchState = is_array($search ?? null) ? $search : ['query' => '', 'results' => [], 'result_count' => 0];
    $title = trim((string) ($block['title'] ?? '')) ?: public_text('search.title', 'Search', $locale);
    $placeholder = trim((string) ($block['placeholder'] ?? '')) ?: public_text('search.placeholder', 'Search the website', $locale);
    $buttonLabel = trim((string) ($block['button_label'] ?? '')) ?: public_text('search.button', 'Search', $locale);
@endphp

<section class="rw-public-search" role="search">
    <h1 class="rw-public-block__title">{{ $title }}</h1>
    <form class="rw-public-search__form" method="get" action="{{ route('cms.public.localized.search', ['locale' => $locale]) }}">
        <label class="rw-public-search__label" for="rw-public-search-input-{{ $block['runtime_id'] ?? 'site' }}">
            {{ public_text('search.input_label', 'Search query', $locale) }}
        </label>
        <div class="rw-public-search__control">
            <input
                id="rw-public-search-input-{{ $block['runtime_id'] ?? 'site' }}"
                class="rw-public-search__input"
                type="search"
                name="q"
                value="{{ $searchState['query'] ?? '' }}"
                placeholder="{{ $placeholder }}"
            />
            <button class="rw-public-button rw-public-search__button" type="submit">{{ $buttonLabel }}</button>
        </div>
    </form>

    @if (! empty($searchState['query']))
        <p class="rw-public-search__meta">
            {{ public_text('search.results_label', 'Results', $locale) }}: {{ (int) ($searchState['result_count'] ?? 0) }}
        </p>
        <div class="rw-public-search__results">
            @forelse (($searchState['results'] ?? []) as $result)
                <article class="rw-public-search__result">
                    <h2 class="rw-public-search__result-title">
                        <a href="{{ $result['canonical_url'] ?? '#' }}">{{ $result['title'] ?? '' }}</a>
                    </h2>
                    @if (! empty($result['snippet']))
                        <p class="rw-public-search__snippet">{{ $result['snippet'] }}</p>
                    @endif
                    @if (! empty($result['markdown_url']))
                        <a class="rw-public-search__markdown" href="{{ $result['markdown_url'] }}">{{ public_text('search.markdown_link', 'Markdown', $locale) }}</a>
                    @endif
                </article>
            @empty
                <p class="rw-public-search__empty">{{ public_text('search.empty', 'No results found.', $locale) }}</p>
            @endforelse
        </div>
    @endif
</section>
