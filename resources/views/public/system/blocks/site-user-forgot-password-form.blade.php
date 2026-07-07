@php
    $locale = $site['current_locale'] ?? null;
@endphp

<section class="rw-public-account rw-public-account--forgot-password">
    <h1 class="rw-public-account__title">{{ public_text('account.forgot_password.title', 'Forgot password', $locale) }}</h1>
    <p class="rw-public-account__intro">{{ public_text('account.forgot_password.intro', 'Enter your email address and we will send you a password reset link.', $locale) }}</p>

    <form class="rw-public-form" method="POST" action="{{ route('site-user.password.email') }}">
        @csrf
        @include('public.system.partials.site-user-system-form-fields', [
            'systemKey' => 'site_user_forgot_password',
            'locale' => $locale,
            'idPrefix' => 'site-user-forgot',
        ])

        <div class="rw-public-form__actions">
            <button class="rw-public-button rw-public-button--primary" type="submit">{{ public_text('account.forgot_password.submit', 'Send reset link', $locale) }}</button>
            <a class="rw-public-link" href="{{ route('site-user.login') }}">{{ public_text('account.actions.back_to_login', 'Back to sign in', $locale) }}</a>
        </div>
    </form>
</section>
