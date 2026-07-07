<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $seo['title'] ?? ($site['name'] ?? config('app.name')) }}</title>
@if (! empty($seo['description']))
    <meta name="description" content="{{ $seo['description'] }}">
@endif
<meta name="robots" content="{{ $seo['robots'] ?? 'index,follow' }}">
@if (! empty($seo['canonical_url']))
    <link rel="canonical" href="{{ $seo['canonical_url'] }}">
@endif
@if (! empty($site['multilingual_enabled']))
    @foreach (($translations ?? []) as $translation)
        <link rel="alternate" hreflang="{{ $translation['locale'] }}" href="{{ url($translation['url']) }}">
    @endforeach
@endif
<meta property="og:type" content="{{ $seo['og_type'] ?? 'website' }}">
@if (! empty($site['name']))
    <meta property="og:site_name" content="{{ $site['name'] }}">
@endif
@if (! empty($seo['og_locale']))
    <meta property="og:locale" content="{{ $seo['og_locale'] }}">
@endif
<meta property="og:title" content="{{ $seo['og_title'] ?? ($seo['title'] ?? '') }}">
@if (! empty($seo['og_description']))
    <meta property="og:description" content="{{ $seo['og_description'] }}">
@endif
@if (! empty($seo['og_url']))
    <meta property="og:url" content="{{ $seo['og_url'] }}">
@endif
@if (! empty($seo['og_image']))
    <meta property="og:image" content="{{ $seo['og_image'] }}">
@endif
@if (! empty($seo['article_published_time']))
    <meta property="article:published_time" content="{{ $seo['article_published_time'] }}">
@endif
@if (! empty($seo['article_modified_time']))
    <meta property="article:modified_time" content="{{ $seo['article_modified_time'] }}">
@endif
@if (! empty($seo['article_section']))
    <meta property="article:section" content="{{ $seo['article_section'] }}">
@endif
@foreach (($seo['article_tags'] ?? []) as $articleTag)
    <meta property="article:tag" content="{{ $articleTag }}">
@endforeach
<meta name="twitter:card" content="{{ $seo['twitter_card'] ?? 'summary' }}">
<meta name="twitter:title" content="{{ $seo['twitter_title'] ?? ($seo['title'] ?? '') }}">
@if (! empty($seo['twitter_description']))
    <meta name="twitter:description" content="{{ $seo['twitter_description'] }}">
@endif
@if (! empty($seo['twitter_image']))
    <meta name="twitter:image" content="{{ $seo['twitter_image'] }}">
@endif
@if (! empty($seo['json_ld']))
    <script type="application/ld+json">{!! $seo['json_ld'] !!}</script>
@endif
