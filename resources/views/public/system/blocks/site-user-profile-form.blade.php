@php
    $locale = $site['current_locale'] ?? null;
    $siteUser = auth('site_user')->user();
    $profile = $siteUser?->profile;
    $profileFieldValues = $siteUser?->profileFieldValues()->pluck('value', 'profile_field_key')->all() ?? [];
@endphp

<section class="rw-public-account rw-public-account--profile">
    <h1 class="rw-public-account__title">{{ public_text('account.profile.title', 'Profile', $locale) }}</h1>

    @if ($siteUser)
        <form class="rw-public-form" method="POST" action="{{ route('site-user.profile.update') }}">
            @csrf
            @include('public.system.partials.site-user-system-form-fields', [
                'systemKey' => 'site_user_profile',
                'locale' => $locale,
                'idPrefix' => 'site-user-profile',
                'values' => [
                    'name' => $siteUser->name,
                    'first_name' => $profile?->first_name,
                    'last_name' => $profile?->last_name,
                    'phone' => $profile?->phone,
                    'marketing_opt_in' => $profile?->marketing_opt_in,
                ],
                'profileFieldValues' => $profileFieldValues,
            ])

            <div class="rw-public-form__actions">
                <button class="rw-public-button rw-public-button--primary" type="submit">{{ public_text('account.profile.submit', 'Save profile', $locale) }}</button>
            </div>
        </form>
    @else
        <p>{{ public_text('account.profile.guest', 'Sign in to manage your profile.', $locale) }}</p>
    @endif
</section>
