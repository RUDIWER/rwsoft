@php
    $classes = [
        'note' => 'rw-docs-admonition rw-docs-admonition--note',
        'tip' => 'rw-docs-admonition rw-docs-admonition--tip',
        'info' => 'rw-docs-admonition rw-docs-admonition--info',
        'warning' => 'rw-docs-admonition rw-docs-admonition--warning',
        'danger' => 'rw-docs-admonition rw-docs-admonition--danger',
    ];
    $icons = [
        'note' => 'mdi-information-outline',
        'tip' => 'mdi-lightbulb-on-outline',
        'info' => 'mdi-information-outline',
        'warning' => 'mdi-alert-outline',
        'danger' => 'mdi-alert-circle-outline',
    ];
@endphp

<aside class="{{ $classes[$type] ?? $classes['note'] }}">
    <div class="rw-docs-admonition__title">
        <span class="mdi {{ $icons[$type] ?? $icons['note'] }}" aria-hidden="true"></span>
        <span>{{ $title }}</span>
    </div>
    <div class="rw-docs-admonition__body rw-public-prose">
        {!! $bodyHtml !!}
    </div>
</aside>
