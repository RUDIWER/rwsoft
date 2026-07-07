@php
    $flag = is_array($translation['flag'] ?? null) ? $translation['flag'] : null;
    $flagUrl = is_string($flag['url'] ?? null) ? trim((string) $flag['url']) : '';
    $textLabel = $labelFor($translation, $labelDisplay);
    $accessibleLabel = trim((string) ($translation['native_name'] ?? $translation['name'] ?? $textLabel));
    $hideTextLabel = $labelDisplay === 'flag_only' && $flagUrl !== '';
@endphp

<a
    class="rw-public-language-menu__link {{ ! empty($translation['active']) ? 'is-active' : '' }}"
    href="{{ $translation['url'] ?? '#' }}"
    hreflang="{{ $translation['locale'] }}"
    @if (! empty($translation['active'])) aria-current="page" @endif
    @if ($hideTextLabel) aria-label="{{ $accessibleLabel }}" @endif
>
    @if ($usesFlag && $flagUrl !== '')
        <img
            class="rw-public-language-menu__flag"
            src="{{ $flagUrl }}"
            alt=""
            loading="lazy"
        >
    @endif
    <span class="rw-public-language-menu__label {{ $hideTextLabel ? 'rw-public-language-menu__label--visually-hidden' : '' }}">
        {{ $textLabel }}
    </span>
    @if (! empty($translation['active']))
        <span class="rw-public-language-menu__check mdi mdi-check" aria-hidden="true"></span>
    @endif
</a>
