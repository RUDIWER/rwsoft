@php
    $locale = $site['current_locale'] ?? null;
    $siteUser = auth('site_user')->user();
@endphp

<section class="rw-public-account rw-public-account--dashboard">
    <h1 class="rw-public-account__title">{{ public_text('account.dashboard.title', 'Dashboard', $locale) }}</h1>

    @if ($siteUser)
        <p class="rw-public-account__intro">
            {{ public_text('account.dashboard.welcome', 'Welcome back,', $locale) }} {{ $siteUser->name }}.
        </p>

        <div class="rw-public-account__cards">
            <article class="rw-public-card">
                <h2 class="rw-public-card__title">{{ public_text('account.dashboard.profile_title', 'Profile', $locale) }}</h2>
                <p>{{ $siteUser->email }}</p>
                <a class="rw-public-link" href="{{ route('site-user.profile') }}">{{ public_text('account.dashboard.profile_link', 'Manage profile', $locale) }}</a>
            </article>

            <article class="rw-public-card">
                <h2 class="rw-public-card__title">{{ public_text('account.dashboard.security_title', 'Security', $locale) }}</h2>
                <p>{{ public_text('account.dashboard.security_text', 'Manage email verification, two-factor authentication and active sessions.', $locale) }}</p>
                <a class="rw-public-link" href="{{ route('site-user.security') }}">{{ public_text('account.dashboard.security_link', 'Security settings', $locale) }}</a>
            </article>
        </div>

        @if (! empty($block['downloads']))
            <section class="rw-public-account__downloads">
                <h2 class="rw-public-block__title">{{ public_text('account.dashboard.downloads_title', 'Your downloads', $locale) }}</h2>
                @include('public.system.blocks.download-list', [
                    'block' => [
                        'downloads' => $block['downloads'],
                        'locked_folders' => [],
                        'show_descriptions' => true,
                    ],
                ])
            </section>
        @endif
    @else
        <p>{{ public_text('account.dashboard.guest', 'Sign in to view your dashboard.', $locale) }}</p>
        <a class="rw-public-button rw-public-button--primary" href="{{ route('site-user.login') }}">{{ public_text('account.login.submit', 'Sign in', $locale) }}</a>
    @endif
</section>
