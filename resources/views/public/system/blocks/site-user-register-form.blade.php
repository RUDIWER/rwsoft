@php
    $locale = $site['current_locale'] ?? null;
    $registrationEnabled = app(App\Support\PublicSite\PublicAccountSettings::class)->registrationEnabled();
@endphp

<section class="rw-public-account rw-public-account--register">
    <h1 class="rw-public-account__title">{{ public_text('account.register.title', 'Create account', $locale) }}</h1>

    @unless ($registrationEnabled)
        <p class="rw-public-form__warning">{{ public_text('account.register.disabled', 'Registration is currently closed.', $locale) }}</p>
    @else
        <form class="rw-public-form" method="POST" action="{{ route('site-user.register.store') }}">
            @csrf
            @include('public.system.partials.site-user-system-form-fields', [
                'systemKey' => 'site_user_register',
                'locale' => $locale,
                'idPrefix' => 'site-user-register',
            ])

            <div class="rw-public-form__actions">
                <button class="rw-public-button rw-public-button--primary" type="submit">{{ public_text('account.register.submit', 'Create account', $locale) }}</button>
                <a class="rw-public-link" href="{{ route('site-user.login') }}">{{ public_text('account.register.login_link', 'Already have an account?', $locale) }}</a>
            </div>
        </form>
    @endunless
</section>
