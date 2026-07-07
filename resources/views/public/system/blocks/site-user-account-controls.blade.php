@php
    use App\Support\PublicSite\CmsLanguageSettings;

    $locale = $site['current_locale'] ?? null;
    $siteUser = auth('site_user')->user();
    $languageSettings = app(CmsLanguageSettings::class);
    $accountLoginUrl = $languageSettings->pathPrefix((string) $locale) !== ''
        ? url($languageSettings->pathPrefix((string) $locale).'/account/login')
        : route('site-user.login');
    $guestIcon = (string) ($block['guest_icon'] ?? 'mdi-account-circle-outline');
    $accountIcon = (string) ($block['account_icon'] ?? 'mdi-account-circle-outline');
    $showGuestLabel = filter_var($block['show_guest_label'] ?? true, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
    $showAccountLabel = filter_var($block['show_account_label'] ?? true, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
    $guestLabel = trim((string) ($block['guest_label'] ?? ''));
    $guestLabel = $guestLabel !== '' ? $guestLabel : public_text('account.links.account', 'Account', $locale);
    $accountLabel = trim((string) ($block['account_label'] ?? ''));
    $accountLabel = $accountLabel !== '' ? $accountLabel : public_text('account.links.account', 'Account', $locale);
    $allowedAccountIcons = [
        'none',
        'mdi-account-circle-outline',
        'mdi-account-outline',
        'mdi-login',
        'mdi-lock-outline',
        'mdi-shield-account-outline',
    ];

    if (! in_array($guestIcon, $allowedAccountIcons, true)) {
        $guestIcon = 'mdi-account-circle-outline';
    }

    if (! in_array($accountIcon, $allowedAccountIcons, true)) {
        $accountIcon = 'mdi-account-circle-outline';
    }

    if ($guestIcon === 'none') {
        $showGuestLabel = true;
    }

    if ($accountIcon === 'none') {
        $showAccountLabel = true;
    }
@endphp

<nav class="rw-public-account-controls" aria-label="{{ public_text('account.controls.label', 'Account links', $locale) }}">
    @if ($siteUser)
        <details class="rw-public-account-controls__dropdown">
            <summary class="rw-public-account-controls__summary" aria-label="{{ $accountLabel }}" title="{{ $accountLabel }}">
                @if ($accountIcon !== 'none')
                    <span class="rw-public-account-controls__icon mdi {{ $accountIcon }}" aria-hidden="true"></span>
                @endif
                @if ($showAccountLabel)
                    <span class="rw-public-account-controls__label">{{ $accountLabel }}</span>
                @endif
            </summary>
            <div class="rw-public-account-controls__dropdown-list">
                <a class="rw-public-account-controls__link rw-public-account-controls__link--dashboard" href="{{ route('site-user.dashboard') }}">
                    {{ public_text('account.nav.dashboard', 'Dashboard', $locale) }}
                </a>
                <a class="rw-public-account-controls__link" href="{{ route('site-user.profile') }}">
                    {{ public_text('account.nav.profile', 'Profile', $locale) }}
                </a>
                <a class="rw-public-account-controls__link" href="{{ route('site-user.security') }}">
                    {{ public_text('account.nav.security', 'Security', $locale) }}
                </a>
                <form class="rw-public-account-controls__logout" method="POST" action="{{ route('site-user.logout') }}">
                    @csrf
                    <button class="rw-public-account-controls__link rw-public-account-controls__button" type="submit">
                        {{ public_text('account.links.logout', 'Sign out', $locale) }}
                    </button>
                </form>
            </div>
        </details>
    @else
        <a class="rw-public-account-controls__link rw-public-account-controls__link--login rw-public-login" href="{{ $accountLoginUrl }}" aria-label="{{ $guestLabel }}" title="{{ $guestLabel }}">
            @if ($guestIcon !== 'none')
                <span class="rw-public-account-controls__icon mdi {{ $guestIcon }}" aria-hidden="true"></span>
            @endif
            @if ($showGuestLabel)
                <span class="rw-public-account-controls__label">{{ $guestLabel }}</span>
            @endif
        </a>
    @endif
</nav>
