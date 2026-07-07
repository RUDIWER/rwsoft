@php
    $locale = $site['current_locale'] ?? null;
    $registrationEnabled = app(App\Support\PublicSite\PublicAccountSettings::class)->registrationEnabled();
    $showRegisterFirst = request()->routeIs('site-user.register') || old('_account_action') === 'register';
@endphp

<section class="rw-public-account rw-public-account--auth-panel">
    <header class="rw-public-account__header">
        <p class="rw-public-eyebrow">{{ public_text('account.auth_panel.eyebrow', 'Website account', $locale) }}</p>
        <h1 class="rw-public-account__title">{{ public_text('account.auth_panel.title', 'Sign in or create an account', $locale) }}</h1>
        <p class="rw-public-account__intro">{{ public_text('account.auth_panel.intro', 'Choose whether you already have an account or want to create a new one.', $locale) }}</p>
    </header>

    <article class="rw-public-card rw-public-account__auth-card rw-public-account__auth-card--tabbed">
        <input
            id="site-user-auth-tab-login"
            class="rw-public-account__tab-input"
            type="radio"
            name="site_user_auth_tab"
            @checked(! $showRegisterFirst)
        >
        <input
            id="site-user-auth-tab-register"
            class="rw-public-account__tab-input"
            type="radio"
            name="site_user_auth_tab"
            @checked($showRegisterFirst)
        >

        <div class="rw-public-account__tabs" role="tablist" aria-label="{{ public_text('account.auth_panel.tabs_label', 'Account choice', $locale) }}">
            <label class="rw-public-account__tab rw-public-account__tab--login" for="site-user-auth-tab-login" role="tab">
                {{ public_text('account.login.title', 'Sign in', $locale) }}
            </label>
            <label class="rw-public-account__tab rw-public-account__tab--register" for="site-user-auth-tab-register" role="tab">
                {{ public_text('account.register.title', 'Create account', $locale) }}
            </label>
        </div>

        <div class="rw-public-account__tab-panel rw-public-account__tab-panel--login" role="tabpanel">
            <h2 class="rw-public-card__title">{{ public_text('account.login.title', 'Sign in', $locale) }}</h2>
            <p class="rw-public-account__intro">{{ public_text('account.auth_panel.login_intro', 'Use your email address and password to continue.', $locale) }}</p>

            <form class="rw-public-form" method="POST" action="{{ route('site-user.login.store') }}">
                @csrf
                <input type="hidden" name="_account_action" value="login">
                @include('public.system.partials.site-user-system-form-fields', [
                    'systemKey' => 'site_user_login',
                    'locale' => $locale,
                    'idPrefix' => 'site-user-auth-login',
                    'accountAction' => 'login',
                    'values' => old('_account_action') === 'login' ? ['email' => old('email'), 'remember' => old('remember')] : [],
                ])

                <div class="rw-public-form__actions">
                    <button class="rw-public-button rw-public-button--primary" type="submit">{{ public_text('account.login.submit', 'Sign in', $locale) }}</button>
                    <a class="rw-public-link" href="{{ route('site-user.password.request') }}">{{ public_text('account.login.forgot_password', 'Forgot your password?', $locale) }}</a>
                </div>
            </form>
        </div>

        <div class="rw-public-account__tab-panel rw-public-account__tab-panel--register" role="tabpanel">
            <h2 class="rw-public-card__title">{{ public_text('account.register.title', 'Create account', $locale) }}</h2>
            <p class="rw-public-account__intro">{{ public_text('account.auth_panel.register_intro', 'New here? Create a website account to continue.', $locale) }}</p>

            @unless ($registrationEnabled)
                <p class="rw-public-form__warning">{{ public_text('account.register.disabled', 'Registration is currently closed.', $locale) }}</p>
            @else
                <form class="rw-public-form" method="POST" action="{{ route('site-user.register.store') }}">
                    @csrf
                    <input type="hidden" name="_account_action" value="register">
                    @include('public.system.partials.site-user-system-form-fields', [
                        'systemKey' => 'site_user_register',
                        'locale' => $locale,
                        'idPrefix' => 'site-user-auth-register',
                        'accountAction' => 'register',
                        'values' => old('_account_action') === 'register' ? ['name' => old('name'), 'email' => old('email')] : [],
                    ])

                    <div class="rw-public-form__actions">
                        <button class="rw-public-button rw-public-button--primary" type="submit">{{ public_text('account.register.submit', 'Create account', $locale) }}</button>
                    </div>
                </form>
            @endunless
        </div>
    </article>
</section>
