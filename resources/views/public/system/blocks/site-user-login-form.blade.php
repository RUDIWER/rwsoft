@php
    $locale = $site['current_locale'] ?? null;
@endphp

<section class="rw-public-account rw-public-account--login">
    <h1 class="rw-public-account__title">{{ public_text('account.login.title', 'Sign in', $locale) }}</h1>

    <form class="rw-public-form" method="POST" action="{{ route('site-user.login.store') }}">
        @csrf
        @include('public.system.partials.site-user-system-form-fields', [
            'systemKey' => 'site_user_login',
            'locale' => $locale,
            'idPrefix' => 'site-user-login',
        ])

        <div class="rw-public-form__actions">
            <button class="rw-public-button rw-public-button--primary" type="submit">{{ public_text('account.login.submit', 'Sign in', $locale) }}</button>
            <a class="rw-public-link" href="{{ route('site-user.password.request') }}">{{ public_text('account.login.forgot_password', 'Forgot your password?', $locale) }}</a>
        </div>
    </form>
</section>
