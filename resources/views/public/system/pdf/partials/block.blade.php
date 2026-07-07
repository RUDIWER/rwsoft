@php
    $rendererKey = (string) ($block['renderer_key'] ?? '');
@endphp

@if (in_array($rendererKey, ['text', 'feature_card', 'testimonial', 'address_block'], true))
    <section class="pdf-block">
        @if (! empty($block['title']))
            <h2>{{ $block['title'] }}</h2>
        @endif

        @if (! empty($block['subtitle']))
            <p class="pdf-muted">{{ $block['subtitle'] }}</p>
        @endif

        @if (! empty($block['text']))
            <p>{{ $block['text'] }}</p>
        @endif

        @if (! empty($block['description']))
            <p>{{ $block['description'] }}</p>
        @endif
    </section>
@elseif ($rendererKey === 'rich_text')
    <section class="pdf-block">
        @if (! empty($block['title']))
            <h2>{{ $block['title'] }}</h2>
        @endif

        @if (! empty($block['html']))
            {!! $block['html'] !!}
        @endif
    </section>
@elseif ($rendererKey === 'markdown_text')
    <section class="pdf-block">
        @if (! empty($block['title']))
            <h2>{{ $block['title'] }}</h2>
        @endif

        @if (! empty($block['markdown']))
            <p>{!! nl2br(e($block['markdown'])) !!}</p>
        @elseif (! empty($block['text']))
            <p>{!! nl2br(e($block['text'])) !!}</p>
        @endif
    </section>
@elseif ($rendererKey === 'image' && ! empty($block['media']['url']))
    <section class="pdf-block">
        <figure>
            <img src="{{ $block['media']['url'] }}" alt="{{ $block['media']['alt_text'] ?? ($payload['title'] ?? '') }}">

            @if (! empty($block['caption']) || ! empty($block['media']['caption']))
                <figcaption class="pdf-muted">{{ $block['caption'] ?: $block['media']['caption'] }}</figcaption>
            @endif
        </figure>
    </section>
@elseif ($rendererKey === 'quote')
    <section class="pdf-block">
        <blockquote class="pdf-quote">
            @if (! empty($block['text']))
                <p>{{ $block['text'] }}</p>
            @endif

            @if (! empty($block['source']))
                <p class="pdf-muted">{{ $block['source'] }}</p>
            @endif
        </blockquote>
    </section>
@elseif (in_array($rendererKey, ['button', 'site_button'], true))
    @if (! empty($block['url']) && ! empty($block['label']))
        <section class="pdf-block">
            <p><a href="{{ $block['url'] }}">{{ $block['label'] }}</a></p>
        </section>
    @endif
@elseif (in_array($rendererKey, ['list_grid', 'list_rows', 'content_list'], true))
    <section class="pdf-block">
        @if (! empty($block['title']))
            <h2>{{ $block['title'] }}</h2>
        @endif

        @if (! empty($block['items']) && is_array($block['items']))
            @foreach ($block['items'] as $item)
                <article class="pdf-card">
                    <h3>{{ $item['title'] ?? '' }}</h3>

                    @if (! empty($item['excerpt']))
                        <p>{{ $item['excerpt'] }}</p>
                    @elseif (! empty($item['description']))
                        <p>{{ $item['description'] }}</p>
                    @endif

                    @if (! empty($item['url']))
                        <p><a href="{{ $item['url'] }}">{{ $item['url'] }}</a></p>
                    @endif
                </article>
            @endforeach
        @endif
    </section>
@elseif ($rendererKey === 'download_list')
    <section class="pdf-block">
        @if (! empty($block['title']))
            <h2>{{ $block['title'] }}</h2>
        @endif

        @if (! empty($block['downloads']) && is_array($block['downloads']))
            <ul class="pdf-list">
                @foreach ($block['downloads'] as $download)
                    <li>
                        @if (! empty($download['download_url']))
                            <a href="{{ $download['download_url'] }}">{{ $download['title'] ?? $download['filename'] ?? $download['download_url'] }}</a>
                        @elseif (! empty($download['url']))
                            <a href="{{ $download['url'] }}">{{ $download['title'] ?? $download['filename'] ?? $download['url'] }}</a>
                        @else
                            {{ $download['title'] ?? $download['filename'] ?? '' }}
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
@elseif ($rendererKey === 'accordion' && ! empty($block['items']) && is_array($block['items']))
    <section class="pdf-block">
        @if (! empty($block['title']))
            <h2>{{ $block['title'] }}</h2>
        @endif

        @foreach ($block['items'] as $item)
            <article class="pdf-card">
                <h3>{{ $item['title'] ?? '' }}</h3>
                @if (! empty($item['content']))
                    <p>{{ $item['content'] }}</p>
                @endif
            </article>
        @endforeach
    </section>
@elseif ($rendererKey === 'stats' && ! empty($block['items']) && is_array($block['items']))
    <section class="pdf-block">
        @if (! empty($block['title']))
            <h2>{{ $block['title'] }}</h2>
        @endif

        <ul class="pdf-list">
            @foreach ($block['items'] as $item)
                <li><strong>{{ $item['value'] ?? '' }}{{ $item['suffix'] ?? '' }}</strong> {{ $item['label'] ?? '' }}</li>
            @endforeach
        </ul>
    </section>
@elseif ($rendererKey === 'logo_strip' && ! empty($block['items']) && is_array($block['items']))
    <section class="pdf-block">
        @if (! empty($block['title']))
            <h2>{{ $block['title'] }}</h2>
        @endif

        <ul class="pdf-list">
            @foreach ($block['items'] as $item)
                <li>{{ $item['title'] ?? $item['name'] ?? '' }}</li>
            @endforeach
        </ul>
    </section>
@elseif ($rendererKey === 'form')
    <section class="pdf-block">
        @if (! empty($block['title']))
            <h2>{{ $block['title'] }}</h2>
        @endif

        <p class="pdf-muted">{{ public_text('pdf.form_available_online', 'This form is available on the website.', $payload['locale'] ?? null) }}</p>

        @if (! empty($payload['url']))
            <p><a href="{{ $payload['url'] }}">{{ $payload['url'] }}</a></p>
        @endif
    </section>
@elseif ($rendererKey === 'video')
    <section class="pdf-block">
        @if (! empty($block['title']))
            <h2>{{ $block['title'] }}</h2>
        @endif

        @if (! empty($block['embed_url']))
            <p><a href="{{ $block['embed_url'] }}">{{ $block['embed_url'] }}</a></p>
        @elseif (! empty($block['url']))
            <p><a href="{{ $block['url'] }}">{{ $block['url'] }}</a></p>
        @endif
    </section>
@endif
