@if (! empty($block['items']) && (count($block['items']) > 1 || ! empty($block['show_on_home'])))
    <nav class="rw-public-breadcrumb{{ ! empty($block['compact']) ? ' rw-public-breadcrumb--compact' : '' }}" aria-label="{{ public_text('breadcrumb.label', 'Breadcrumb', $site['current_locale'] ?? null) }}">
        <ol class="rw-public-breadcrumb__list">
            @foreach ($block['items'] as $item)
                <li class="rw-public-breadcrumb__item{{ ! empty($item['is_current']) ? ' rw-public-breadcrumb__item--current' : '' }}">
                    @if (empty($item['is_current']) && ! empty($item['url']))
                        @if ($loop->first && ! empty($block['home_icon']))
                            <a class="rw-public-breadcrumb__link" href="{{ $item['url'] }}">
                                <span class="mdi {{ $block['home_icon'] }}" aria-hidden="true"></span>
                                <span>{{ $item['label'] }}</span>
                            </a>
                        @else
                            <a class="rw-public-breadcrumb__link" href="{{ $item['url'] }}">{{ $item['label'] }}</a>
                        @endif
                    @else
                        @if ($loop->first && ! empty($block['home_icon']))
                            <span class="rw-public-breadcrumb__current" aria-current="page">
                                <span class="mdi {{ $block['home_icon'] }}" aria-hidden="true"></span>
                                <span>{{ $item['label'] }}</span>
                            </span>
                        @else
                            <span class="rw-public-breadcrumb__current" aria-current="page">{{ $item['label'] }}</span>
                        @endif
                    @endif
                    @if (! $loop->last)
                        <span class="rw-public-breadcrumb__separator" aria-hidden="true">{{ $block['separator'] ?? '›' }}</span>
                    @endif
                </li>
            @endforeach
        </ol>
    </nav>
@endif
