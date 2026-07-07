@extends('public.system.layouts.app')

@section('content')
    <section class="rw-public-hero">
        <h1 class="rw-public-title">{{ $postIndexTitle ?? public_text('post_index.title', 'Blogs', $site['current_locale'] ?? null) }}</h1>
        <p class="rw-public-lead">{{ $postIndexLead ?? public_text('post_index.lead', 'Laatste gepubliceerde blogs en updates.', $site['current_locale'] ?? null) }}</p>
    </section>

    @if (! empty($posts))
        <section class="rw-public-post-grid">
            @foreach ($posts as $post)
                <a class="rw-public-card rw-public-post-card" href="{{ $post['url'] }}">
                    @if (! empty($post['featured_media']))
                        <img
                            class="rw-public-image"
                            src="{{ $post['featured_media']['url'] }}"
                            alt="{{ $post['featured_media']['alt_text'] ?: $post['title'] }}"
                            loading="lazy"
                        >
                    @endif
                    <div class="rw-public-post-card__body">
                        @if (! empty($post['taxonomy_items'] ?? $post['categories'] ?? []))
                            <div class="rw-public-chip-list">
                                @foreach (($post['taxonomy_items'] ?? $post['categories']) as $taxonomyItem)
                                    <span class="rw-public-chip">{{ $post['taxonomy_prefix'] ?? '' }}{{ $taxonomyItem['title'] }}</span>
                                @endforeach
                            </div>
                        @endif
                        @if (! empty($post['published_at']))
                            <div class="rw-public-post-meta">{{ $post['published_at'] }}</div>
                        @endif
                        <h2 class="rw-public-post-card__title">{{ $post['title'] }}</h2>
                        @if (! empty($post['excerpt']))
                            <p class="rw-public-post-card__excerpt">{{ $post['excerpt'] }}</p>
                        @endif
                        <span class="rw-public-button">{{ public_text('post_index.read_more', 'Lees meer', $site['current_locale'] ?? null) }}</span>
                    </div>
                </a>
            @endforeach
        </section>
    @else
        <section class="rw-public-block">
            <p class="rw-public-block__text">{{ public_text('post_index.empty', 'Er zijn nog geen gepubliceerde blogs.', $site['current_locale'] ?? null) }}</p>
        </section>
    @endif
@endsection
