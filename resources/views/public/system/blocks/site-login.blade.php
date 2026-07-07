@php
    $isAuthenticated = auth()->check();
    $url = $isAuthenticated ? route('dashboard') : route('login');
    $label = $isAuthenticated
        ? ($block['account_label'] ?: public_text('auth.account', 'Account', $site['current_locale'] ?? null))
        : ($block['login_label'] ?: public_text('auth.login', 'Login', $site['current_locale'] ?? null));
@endphp

<a class="rw-public-login" href="{{ $url }}">{{ $label }}</a>
