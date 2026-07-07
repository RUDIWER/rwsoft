@if (! empty($site['favicon']['favicon_32_url']))
    <link rel="icon" type="image/png" sizes="32x32" href="{{ $site['favicon']['favicon_32_url'] }}">
@endif
@if (! empty($site['favicon']['favicon_192_url']))
    <link rel="icon" type="image/png" sizes="192x192" href="{{ $site['favicon']['favicon_192_url'] }}">
@endif
@if (! empty($site['favicon']['apple_touch_icon_url']))
    <link rel="apple-touch-icon" sizes="180x180" href="{{ $site['favicon']['apple_touch_icon_url'] }}">
@endif
