@extends('public.system.layouts.app')

@section('content')
    <section class="rw-public-hero">
        <h1 class="rw-public-title">{{ $postItem['title'] }}</h1>
        @if (! empty($postItem['published_at']))
            <div class="rw-public-post-meta">Gepubliceerd op {{ $postItem['published_at'] }}</div>
        @endif
        @if (! empty($postItem['excerpt']))
            <p class="rw-public-lead">{{ $postItem['excerpt'] }}</p>
        @endif
        @if (! empty($postItem['categories']))
            <div class="rw-public-chip-list">
                @foreach ($postItem['categories'] as $category)
                    <span class="rw-public-chip">{{ $category['title'] }}</span>
                @endforeach
            </div>
        @endif
    </section>

    @if (! empty($postItem['featured_media']))
        <section class="rw-public-block--image">
            <figure>
                <img
                    class="rw-public-image"
                    src="{{ $postItem['featured_media']['url'] }}"
                    alt="{{ $postItem['featured_media']['alt_text'] ?: $postItem['title'] }}"
                >
                @if (! empty($postItem['featured_media']['caption']))
                    <figcaption class="rw-public-image-caption">{{ $postItem['featured_media']['caption'] }}</figcaption>
                @endif
            </figure>
        </section>
    @endif

    @include('public.system.partials.blocks', ['blocks' => $blocks, 'contentItem' => $postItem])

    @if (! empty($postItem['tags']))
        <section class="rw-public-tags rw-public-chip-list">
            @foreach ($postItem['tags'] as $tag)
                <a class="rw-public-chip" href="{{ $tag['url'] }}">#{{ $tag['title'] }}</a>
            @endforeach
        </section>
    @endif
@endsection
