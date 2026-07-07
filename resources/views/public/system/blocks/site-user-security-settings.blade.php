@php
    $locale = $site['current_locale'] ?? null;
    $siteUser = auth('site_user')->user();
    $settings = app(App\Support\PublicSite\PublicAccountSettings::class);
    $sessionTracker = app(App\Actions\PublicSite\TrackSiteUserSessionAction::class);
    $twoFactorEnabled = $settings->twoFactorEnabled();
    $hasTwoFactorSecret = $siteUser && ! empty($siteUser->two_factor_secret);
    $hasConfirmedTwoFactor = $siteUser && $siteUser->hasEnabledTwoFactorAuthentication();
    $currentSessionHash = request()->hasSession() ? $sessionTracker->currentTokenHash(request()) : null;
    $activeSessions = $siteUser ? $sessionTracker->activeSessionsFor($siteUser) : collect();
@endphp

<section class="rw-public-account rw-public-account--security">
    <h1 class="rw-public-account__title">{{ public_text('account.security.title', 'Security', $locale) }}</h1>

    @if ($siteUser)
        <article class="rw-public-card">
            <h2 class="rw-public-card__title">{{ public_text('account.security.email_title', 'Email verification', $locale) }}</h2>
            @if ($siteUser->hasVerifiedEmail())
                <p>{{ public_text('account.security.email_verified', 'Your email address is verified.', $locale) }}</p>
            @else
                <p>{{ public_text('account.security.email_unverified', 'Your email address is not verified yet.', $locale) }}</p>
                <form method="POST" action="{{ route('site-user.verification.send') }}">
                    @csrf
                    <button class="rw-public-button rw-public-button--secondary" type="submit">{{ public_text('account.security.resend_verification', 'Send verification email', $locale) }}</button>
                </form>
            @endif
        </article>

        <article class="rw-public-card">
            <h2 class="rw-public-card__title">{{ public_text('account.security.two_factor_title', 'Two-factor authentication', $locale) }}</h2>
            @unless ($twoFactorEnabled)
                <p>{{ public_text('account.security.two_factor_disabled_by_site', 'Two-factor authentication is not enabled for this website.', $locale) }}</p>
            @else
                @if ($hasConfirmedTwoFactor)
                    <p>{{ public_text('account.security.two_factor_enabled', 'Two-factor authentication is enabled.', $locale) }}</p>
                    <form method="POST" action="{{ route('site-user.two-factor.disable') }}">
                        @csrf
                        <button class="rw-public-button rw-public-button--secondary" type="submit">{{ public_text('account.security.disable_two_factor', 'Disable two-factor authentication', $locale) }}</button>
                    </form>
                @elseif ($hasTwoFactorSecret)
                    <p>{{ public_text('account.security.two_factor_confirm_intro', 'Scan the QR code with your authenticator app and confirm the setup with a code.', $locale) }}</p>
                    <div class="rw-public-account__qr-code">{!! $siteUser->twoFactorQrCodeSvg() !!}</div>
                    <form class="rw-public-form" method="POST" action="{{ route('site-user.two-factor.confirm') }}">
                        @csrf
                        <div class="rw-public-form__field">
                            <label class="rw-public-form__label" for="site-user-two-factor-confirm-code">{{ public_text('account.fields.two_factor_code', 'Authentication code', $locale) }}</label>
                            <input id="site-user-two-factor-confirm-code" class="rw-public-form__input" type="text" name="code" inputmode="numeric" autocomplete="one-time-code" required>
                            @error('code')
                                <p class="rw-public-form__error">{{ $message }}</p>
                            @enderror
                        </div>
                        <button class="rw-public-button rw-public-button--primary" type="submit">{{ public_text('account.security.confirm_two_factor', 'Confirm two-factor authentication', $locale) }}</button>
                    </form>
                @else
                    <p>{{ public_text('account.security.two_factor_available', 'Protect your account with an authenticator app.', $locale) }}</p>
                    <form method="POST" action="{{ route('site-user.two-factor.enable') }}">
                        @csrf
                        <button class="rw-public-button rw-public-button--primary" type="submit">{{ public_text('account.security.enable_two_factor', 'Enable two-factor authentication', $locale) }}</button>
                    </form>
                @endif
            @endunless
        </article>

        <article class="rw-public-card">
            <h2 class="rw-public-card__title">{{ public_text('account.security.sessions_title', 'Active sessions', $locale) }}</h2>
            <p>{{ public_text('account.security.sessions_intro', 'Review where your account is currently signed in.', $locale) }}</p>

            @if ($activeSessions->isEmpty())
                <p>{{ public_text('account.security.sessions_empty', 'No active sessions were found.', $locale) }}</p>
            @else
                <div class="rw-public-account__sessions">
                    @foreach ($activeSessions as $session)
                        @php
                            $isCurrentSession = $currentSessionHash !== null && hash_equals($currentSessionHash, (string) $session->session_token_hash);
                            $deviceLabel = $session->deviceLabel();
                        @endphp
                        <div class="rw-public-account__session">
                            <div>
                                <strong>
                                    {{ $deviceLabel !== '' ? $deviceLabel : public_text('account.security.session_unknown_device', 'Unknown device', $locale) }}
                                </strong>
                                @if ($isCurrentSession)
                                    <span class="rw-public-account__session-current">{{ public_text('account.security.session_current', 'Current session', $locale) }}</span>
                                @endif
                            </div>
                            <div class="rw-public-account__session-meta">
                                {{ public_text('account.security.session_last_activity', 'Last activity', $locale) }}:
                                {{ $session->last_activity_at?->format('d/m/Y H:i') ?? '-' }}
                            </div>
                        </div>
                    @endforeach
                </div>

                @if ($activeSessions->count() > 1)
                    <form method="POST" action="{{ route('site-user.sessions.logout-other-devices') }}">
                        @csrf
                        <button class="rw-public-button rw-public-button--secondary" type="submit">
                            {{ public_text('account.security.logout_other_devices', 'Sign out other devices', $locale) }}
                        </button>
                    </form>
                @endif
            @endif
        </article>
    @else
        <p>{{ public_text('account.security.guest', 'Sign in to manage security settings.', $locale) }}</p>
    @endif
</section>
