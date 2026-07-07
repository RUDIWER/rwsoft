<!doctype html>
<html lang="{{ $payload['locale'] ?? app()->getLocale() }}">
    <head>
        <meta charset="utf-8">
        <title>{{ $payload['title'] ?? '' }}</title>
        <style>
            @page { margin: 22mm 18mm; }
            body { color: #111827; font-family: 'DejaVu Sans', sans-serif; font-size: 11px; line-height: 1.55; }
            h1, h2, h3 { color: #0f172a; line-height: 1.2; margin: 0 0 8px; }
            h1 { font-size: 26px; margin-bottom: 10px; }
            h2 { font-size: 18px; margin-top: 18px; }
            h3 { font-size: 14px; margin-top: 14px; }
            p { margin: 0 0 9px; }
            a { color: #1d4ed8; text-decoration: none; }
            img { height: auto; max-width: 100%; }
            .pdf-meta { border-bottom: 1px solid #cbd5e1; color: #475569; margin-bottom: 18px; padding-bottom: 10px; }
            .pdf-lead { color: #334155; font-size: 13px; margin-bottom: 14px; }
            .pdf-block { border-top: 1px solid #e2e8f0; margin-top: 14px; padding-top: 14px; page-break-inside: avoid; }
            .pdf-card { border: 1px solid #cbd5e1; border-radius: 6px; margin-bottom: 10px; padding: 10px; page-break-inside: avoid; }
            .pdf-chip { background: #eff6ff; border-radius: 999px; color: #1d4ed8; display: inline-block; font-size: 10px; margin: 0 4px 4px 0; padding: 2px 7px; }
            .pdf-quote { border-left: 3px solid #93c5fd; color: #334155; margin: 0; padding: 2px 0 2px 12px; }
            .pdf-muted { color: #64748b; }
            .pdf-list { margin: 8px 0 0 18px; padding: 0; }
            .pdf-list li { margin-bottom: 5px; }
            .pdf-footer { border-top: 1px solid #e2e8f0; color: #64748b; font-size: 9px; margin-top: 24px; padding-top: 8px; }
        </style>
    </head>
    <body>
        <header>
            <h1>{{ $payload['title'] ?? '' }}</h1>

            <div class="pdf-meta">
                @if (! empty($payload['published_at']))
                    <div>{{ public_text('pdf.published_at', 'Published at', $payload['locale'] ?? null) }}: {{ $payload['published_at'] }}</div>
                @endif

                @if (! empty($payload['url']))
                    <div>{{ public_text('pdf.source_url', 'Source URL', $payload['locale'] ?? null) }}: {{ $payload['url'] }}</div>
                @endif
            </div>

            @if (! empty($payload['description']))
                <p class="pdf-lead">{{ $payload['description'] }}</p>
            @endif

            @if (! empty($payload['meta']['categories']))
                <div>
                    @foreach ($payload['meta']['categories'] as $category)
                        <span class="pdf-chip">{{ $category['title'] }}</span>
                    @endforeach
                </div>
            @endif

            @if (! empty($payload['meta']['tags']))
                <div>
                    @foreach ($payload['meta']['tags'] as $tag)
                        <span class="pdf-chip">#{{ $tag['title'] }}</span>
                    @endforeach
                </div>
            @endif
        </header>

        @foreach (($payload['blocks'] ?? []) as $block)
            @include('public.system.pdf.partials.block', [
                'block' => $block,
                'payload' => $payload,
            ])
        @endforeach

        @if (! empty($payload['items']))
            <section class="pdf-block">
                @foreach ($payload['items'] as $item)
                    <article class="pdf-card">
                        <h2>{{ $item['title'] ?? '' }}</h2>

                        @if (! empty($item['published_at']))
                            <p class="pdf-muted">{{ $item['published_at'] }}</p>
                        @endif

                        @if (! empty($item['taxonomy_items']))
                            <div>
                                @foreach ($item['taxonomy_items'] as $taxonomyItem)
                                    <span class="pdf-chip">{{ $item['taxonomy_prefix'] ?? '' }}{{ $taxonomyItem['title'] }}</span>
                                @endforeach
                            </div>
                        @endif

                        @if (! empty($item['excerpt']))
                            <p>{{ $item['excerpt'] }}</p>
                        @endif

                        @if (! empty($item['url']))
                            <p><a href="{{ $item['url'] }}">{{ $item['url'] }}</a></p>
                        @endif
                    </article>
                @endforeach
            </section>
        @endif

        <footer class="pdf-footer">
            {{ public_text('pdf.generated_from_site', 'Generated from the website.', $payload['locale'] ?? null) }}
        </footer>
    </body>
</html>
