@php
    $locale = $site['current_locale'] ?? null;
@endphp

<section class="rw-public-account rw-public-account--two-factor-challenge">
    <h1 class="rw-public-account__title">{{ public_text('account.two_factor_challenge.title', 'Two-factor authentication', $locale) }}</h1>
    <p class="rw-public-account__intro">{{ public_text('account.two_factor_challenge.intro', 'Enter the authentication code from your authenticator app or use a recovery code.', $locale) }}</p>

    <form class="rw-public-form" method="POST" action="{{ route('site-user.two-factor.challenge.store') }}">
        @csrf
        @include('public.system.partials.site-user-system-form-fields', [
            'systemKey' => 'site_user_two_factor_challenge',
            'locale' => $locale,
            'idPrefix' => 'site-user-two-factor',
        ])

        <div class="rw-public-form__actions">
            <button class="rw-public-button rw-public-button--primary" type="submit">{{ public_text('account.two_factor_challenge.submit', 'Verify', $locale) }}</button>
        </div>
    </form>
</section>
