<?php

return [
    'auth' => [
        'inactive' => 'This account is not active.',
        'failed' => 'These credentials do not match our records.',
        'registration_disabled' => 'Registration is not enabled for this website.',
        'verification_sent' => 'A verification link has been sent to your email address.',
        'email_verified' => 'Your email address has been verified.',
        'email_verification_required' => 'Please verify your email address before continuing.',
        'password_reset_sent' => 'A password reset link has been sent to your email address.',
        'password_reset' => 'Your password has been reset.',
        'profile_saved' => 'Your profile has been saved.',
        'signed_out' => 'You have been signed out.',
    ],
    'system_forms' => [
        'login' => [
            'title' => 'Sign in',
            'submit' => 'Sign in',
            'success' => 'You are signed in.',
        ],
        'register' => [
            'title' => 'Create account',
            'submit' => 'Create account',
            'success' => 'Your account has been created.',
        ],
        'forgot_password' => [
            'title' => 'Forgot password',
            'submit' => 'Send reset link',
            'success' => 'A password reset link has been sent.',
        ],
        'reset_password' => [
            'title' => 'Reset password',
            'submit' => 'Reset password',
            'success' => 'Your password has been reset.',
        ],
        'profile' => [
            'title' => 'Profile',
            'submit' => 'Save profile',
            'success' => 'Your profile has been saved.',
        ],
        'security' => [
            'title' => 'Security',
            'submit' => 'Save security settings',
            'success' => 'Your security settings have been saved.',
        ],
        'two_factor_challenge' => [
            'title' => 'Two-factor authentication',
            'submit' => 'Verify',
            'success' => 'Two-factor authentication has been verified.',
        ],
    ],
    'system_templates' => [
        'auth' => 'Account auth',
        'login' => 'Account sign in',
        'register' => 'Account registration',
        'forgot_password' => 'Account forgot password',
        'reset_password' => 'Account reset password',
        'dashboard' => 'Account dashboard',
        'profile' => 'Account profile',
        'security' => 'Account security',
        'two_factor_challenge' => 'Account two-factor challenge',
    ],
    'fields' => [
        'name' => 'Name',
        'first_name' => 'First name',
        'last_name' => 'Last name',
        'phone' => 'Phone',
        'email' => 'Email address',
        'password' => 'Password',
        'password_confirmation' => 'Confirm password',
        'current_password' => 'Current password',
        'remember' => 'Remember me',
        'marketing_opt_in' => 'I want to receive updates',
        'two_factor_code' => 'Authentication code',
        'recovery_code' => 'Recovery code',
    ],
    'profile_fields' => [
        'company_name' => 'Company name',
        'vat_number' => 'VAT number',
        'customer_type' => 'Customer type',
    ],
    'profile_field_options' => [
        'customer_type' => [
            'private' => 'Private customer',
            'business' => 'Business customer',
        ],
    ],
    'two_factor' => [
        'enabled' => 'Two-factor authentication has been prepared. Confirm it with a code from your authenticator app.',
        'confirmed' => 'Two-factor authentication has been enabled.',
        'disabled' => 'Two-factor authentication has been disabled.',
        'invalid_code' => 'The provided two-factor authentication code was invalid.',
        'required' => 'Please enable two-factor authentication before continuing.',
    ],
    'sessions' => [
        'other_devices_signed_out' => '{0} No other active sessions were found.|{1} One other device has been signed out.|[2,*] :count other devices have been signed out.',
        'revoked' => 'This session has been signed out. Please sign in again.',
    ],
    'mail' => [
        'reset_password' => [
            'subject' => 'Reset your password',
            'intro' => 'You are receiving this email because we received a password reset request for your account.',
            'action' => 'Reset password',
            'outro' => 'If you did not request a password reset, no further action is required.',
        ],
        'verify_email' => [
            'subject' => 'Verify your email address',
            'intro' => 'Please click the button below to verify your email address.',
            'action' => 'Verify email address',
            'outro' => 'If you did not create an account, no further action is required.',
        ],
    ],
];
