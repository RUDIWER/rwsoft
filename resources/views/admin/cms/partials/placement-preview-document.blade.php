@php
    $previewDevice = in_array($previewDevice ?? 'desktop', ['desktop', 'tablet', 'mobile'], true) ? $previewDevice : 'desktop';
@endphp

<!doctype html>
<html class="rw-public-root rw-admin-placement-preview-root rw-admin-placement-preview-root--{{ $previewDevice }}" lang="{{ $locale ?? app()->getLocale() }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <base target="_blank">
        <link rel="stylesheet" href="{{ \Illuminate\Support\Facades\Vite::asset('resources/css/public/system.css') }}">
        @if (! empty($themeCssUrl))
            <link rel="stylesheet" href="{{ $themeCssUrl }}">
        @endif
        <style>
            html,
            body {
                margin: 0;
                min-height: 0;
                width: 100%;
                overflow: hidden;
                background: transparent;
            }

            .rw-admin-placement-preview {
                width: 100%;
                min-height: 0;
                overflow: hidden;
            }

            .rw-admin-placement-preview .rw-public-placement {
                box-sizing: border-box;
                grid-column: auto !important;
                grid-row: auto !important;
                width: 100%;
                min-height: 0;
            }

            .rw-admin-placement-preview__placeholder {
                display: grid;
                gap: 0.25rem;
                min-height: 100%;
                place-content: center;
                border: 1px dashed color-mix(in srgb, var(--rw-public-color-border, #cbd5e1) 70%, transparent);
                border-radius: var(--rw-public-radius-md, 0.75rem);
                background: color-mix(in srgb, var(--rw-public-color-surface-muted, #f8fafc) 92%, transparent);
                padding: 0.75rem;
                text-align: center;
                color: var(--rw-public-color-muted, #64748b);
                font-size: 0.8125rem;
                line-height: 1.35;
            }

            .rw-admin-placement-preview__placeholder strong {
                display: block;
                color: var(--rw-public-color-text, #0f172a);
                font-weight: 700;
            }
        </style>
    </head>
    <body class="rw-public rw-admin-placement-preview-body" data-preview-device="{{ $previewDevice }}">
        <div class="rw-admin-placement-preview">
            @if (! empty($placeholderTitle) || ! empty($placeholderDescription))
                <div class="rw-admin-placement-preview__placeholder">
                    @if (! empty($placeholderTitle))
                        <strong>{{ $placeholderTitle }}</strong>
                    @endif
                    @if (! empty($placeholderDescription))
                        <span>{{ $placeholderDescription }}</span>
                    @endif
                </div>
            @elseif (! empty($placement))
                @include('public.system.partials.placement', [
                    'placement' => $placement,
                    'section' => $section,
                    'contentItem' => $contentItem,
                ])
            @endif
        </div>
    </body>
</html>
