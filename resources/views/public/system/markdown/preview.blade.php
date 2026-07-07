<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, follow">
    <title>{{ $title }}</title>
    <style>
        body { font-family: ui-sans-serif, system-ui, sans-serif; margin: 0; background: #f8fafc; color: #0f172a; }
        main { max-width: 960px; margin: 0 auto; padding: 2rem; }
        nav { display: flex; flex-wrap: wrap; gap: .75rem; margin-bottom: 1rem; }
        a { color: #2563eb; }
        pre { overflow: auto; border: 1px solid #cbd5e1; border-radius: .75rem; background: #fff; padding: 1rem; white-space: pre-wrap; }
    </style>
</head>
<body>
<main>
    <h1>{{ $title }}</h1>
    <nav aria-label="{{ public_text('markdown.preview_actions', 'Markdown actions') }}">
        @if ($htmlUrl)
            <a href="{{ $htmlUrl }}">{{ public_text('markdown.html_link', 'Canonical page') }}</a>
        @endif
        @if ($rawUrl)
            <a href="{{ $rawUrl }}">{{ public_text('markdown.raw_link', 'Raw markdown') }}</a>
        @endif
        @if ($downloadUrl)
            <a href="{{ $downloadUrl }}">{{ public_text('markdown.download_link', 'Download') }}</a>
        @endif
    </nav>
    <pre>{{ $markdown }}</pre>
</main>
</body>
</html>
