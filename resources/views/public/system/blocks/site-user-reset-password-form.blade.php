@php
    $locale = $site['current_locale'] ?? null;
@endphp

<section class="rw-public-account rw-public-account--reset-password">
    <h1 class="rw-public-account__title">{{ public_text('account.reset_password.title', 'Reset password', $locale) }}</h1>

    <form class="rw-public-form" method="POST" action="{{ route('site-user.password.store') }}">
        @csrf
        <input type="hidden" name="token" value="{{ request()->route('token') }}">
        @include('public.system.partials.site-user-system-form-fields', [
            'systemKey' => 'site_user_reset_password',
            'locale' => $locale,
            'idPrefix' => 'site-user-reset',
            'values' => ['email' => request()->query('email')],
        ])

        <div class="rw-public-form__actions">
            <button class="rw-public-button rw-public-button--primary" type="submit">{{ public_text('account.reset_password.submit', 'Reset password', $locale) }}</button>
        </div>
    </form>
</section>
