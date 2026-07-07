@php
    $locale = $site['current_locale'] ?? null;
    $media = is_array($block['media'] ?? null) ? $block['media'] : null;
    $imagePosition = in_array($block['image_position'] ?? null, ['top', 'left', 'right', 'bottom'], true)
        ? $block['image_position']
        : 'top';
    $addressParts = array_filter([
        $block['street'] ?? null,
        trim((string) (($block['postal_code'] ?? '').' '.($block['city'] ?? ''))),
        $block['country'] ?? null,
        $block['country_code'] ?? null,
    ]);
    $phones = is_array($block['phones'] ?? null) ? $block['phones'] : [];
    $emails = is_array($block['emails'] ?? null) ? $block['emails'] : [];
    $customFields = is_array($block['custom_fields'] ?? null) ? $block['custom_fields'] : [];
    $showCompanyName = filter_var($block['show_company_name'] ?? false, FILTER_VALIDATE_BOOL);
    $showAddress = filter_var($block['show_address'] ?? false, FILTER_VALIDATE_BOOL);
    $showPhones = filter_var($block['show_phones'] ?? false, FILTER_VALIDATE_BOOL);
    $showEmails = filter_var($block['show_emails'] ?? false, FILTER_VALIDATE_BOOL);
    $showVatNumber = filter_var($block['show_vat_number'] ?? false, FILTER_VALIDATE_BOOL);
    $showCustomFields = filter_var($block['show_custom_fields'] ?? false, FILTER_VALIDATE_BOOL);
    $hasTextContent = ! empty($block['title'])
        || ($showCompanyName && ! empty($block['company_name']))
        || ($showAddress && $addressParts !== [])
        || ($showPhones && $phones !== [])
        || ($showEmails && $emails !== [])
        || ($showVatNumber && ! empty($block['vat_number']))
        || ($showCustomFields && $customFields !== []);
@endphp

@if ($media || $hasTextContent)
    <article class="rw-public-address rw-public-address--image-{{ $imagePosition }}">
        @if ($media)
            <figure class="rw-public-address__media">
                <img
                    src="{{ $media['url'] }}"
                    alt="{{ $media['alt_text'] ?: ($block['company_name'] ?? ($site['name'] ?? '')) }}"
                    @if (! empty($media['width'])) width="{{ $media['width'] }}" @endif
                    @if (! empty($media['height'])) height="{{ $media['height'] }}" @endif
                    loading="lazy"
                >
            </figure>
        @endif

        @if ($hasTextContent)
            <div class="rw-public-address__content">
                @if (! empty($block['title']))
                    <h2 class="rw-public-address__title">{{ $block['title'] }}</h2>
                @endif

                @if ($showCompanyName && ! empty($block['company_name']))
                    <p class="rw-public-address__company">{{ $block['company_name'] }}</p>
                @endif

                @if ($showAddress && $addressParts !== [])
                    <address class="rw-public-address__address">
                        @foreach ($addressParts as $addressPart)
                            <span>{{ $addressPart }}</span>
                        @endforeach
                    </address>
                @endif

                @if ($showPhones && $phones !== [])
                    <ul class="rw-public-address__list rw-public-address__list--phones">
                        @foreach ($phones as $phone)
                            <li>
                                @if (! empty($phone['label']))
                                    <span class="rw-public-address__label">{{ $phone['label'] }}</span>
                                @endif
                                @if (! empty($phone['href']))
                                    <a href="{{ $phone['href'] }}">{{ $phone['value'] }}</a>
                                @else
                                    <span>{{ $phone['value'] }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if ($showEmails && $emails !== [])
                    <ul class="rw-public-address__list rw-public-address__list--emails">
                        @foreach ($emails as $email)
                            <li>
                                @if (! empty($email['label']))
                                    <span class="rw-public-address__label">{{ $email['label'] }}</span>
                                @endif
                                @if (! empty($email['href']))
                                    <a href="{{ $email['href'] }}">{{ $email['value'] }}</a>
                                @else
                                    <span>{{ $email['value'] }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if ($showVatNumber && ! empty($block['vat_number']))
                    <p class="rw-public-address__vat">
                        <span class="rw-public-address__label">{{ public_text('contact.vat_number_label', 'VAT number', $locale) }}</span>
                        <span>{{ $block['vat_number'] }}</span>
                    </p>
                @endif

                @if ($showCustomFields && $customFields !== [])
                    <dl class="rw-public-address__custom-fields">
                        @foreach ($customFields as $customField)
                            <div>
                                @if (! empty($customField['label']))
                                    <dt>{{ $customField['label'] }}</dt>
                                @endif
                                <dd>{{ $customField['value'] }}</dd>
                            </div>
                        @endforeach
                    </dl>
                @endif

                @if (! empty($block['slots']['actions']))
                    @include('public.system.partials.block-slot', [
                        'slot' => $block['slots']['actions'],
                        'section' => $section ?? [],
                        'contentItem' => $contentItem ?? null,
                    ])
                @endif
            </div>
        @endif
    </article>
@endif
