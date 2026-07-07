<?php

return [
    'auth' => [
        'inactive' => 'Dit account is niet actief.',
        'failed' => 'Deze gegevens komen niet overeen met onze records.',
        'registration_disabled' => 'Registratie is niet ingeschakeld voor deze website.',
        'verification_sent' => 'Er is een verificatielink naar je e-mailadres verstuurd.',
        'email_verified' => 'Je e-mailadres is bevestigd.',
        'email_verification_required' => 'Bevestig je e-mailadres voordat je verdergaat.',
        'password_reset_sent' => 'Er is een wachtwoordresetlink naar je e-mailadres verstuurd.',
        'password_reset' => 'Je wachtwoord is opnieuw ingesteld.',
        'profile_saved' => 'Je profiel is bewaard.',
        'signed_out' => 'Je bent afgemeld.',
    ],
    'system_forms' => [
        'login' => [
            'title' => 'Inloggen',
            'submit' => 'Inloggen',
            'success' => 'Je bent ingelogd.',
        ],
        'register' => [
            'title' => 'Account aanmaken',
            'submit' => 'Account aanmaken',
            'success' => 'Je account is aangemaakt.',
        ],
        'forgot_password' => [
            'title' => 'Wachtwoord vergeten',
            'submit' => 'Resetlink verzenden',
            'success' => 'Er is een wachtwoordresetlink verzonden.',
        ],
        'reset_password' => [
            'title' => 'Wachtwoord opnieuw instellen',
            'submit' => 'Wachtwoord opnieuw instellen',
            'success' => 'Je wachtwoord is opnieuw ingesteld.',
        ],
        'profile' => [
            'title' => 'Profiel',
            'submit' => 'Profiel bewaren',
            'success' => 'Je profiel is bewaard.',
        ],
        'security' => [
            'title' => 'Beveiliging',
            'submit' => 'Beveiliging bewaren',
            'success' => 'Je beveiligingsinstellingen zijn bewaard.',
        ],
        'two_factor_challenge' => [
            'title' => 'Tweestapsauthenticatie',
            'submit' => 'Verifiëren',
            'success' => 'Tweestapsauthenticatie is geverifieerd.',
        ],
    ],
    'system_templates' => [
        'auth' => 'Accountauthenticatie',
        'login' => 'Account inloggen',
        'register' => 'Accountregistratie',
        'forgot_password' => 'Account wachtwoord vergeten',
        'reset_password' => 'Account wachtwoord herstellen',
        'dashboard' => 'Accountdashboard',
        'profile' => 'Accountprofiel',
        'security' => 'Accountbeveiliging',
        'two_factor_challenge' => 'Account tweestapscontrole',
    ],
    'fields' => [
        'name' => 'Naam',
        'first_name' => 'Voornaam',
        'last_name' => 'Achternaam',
        'phone' => 'Telefoon',
        'email' => 'E-mailadres',
        'password' => 'Wachtwoord',
        'password_confirmation' => 'Wachtwoord bevestigen',
        'current_password' => 'Huidig wachtwoord',
        'remember' => 'Onthoud mij',
        'marketing_opt_in' => 'Ik wil updates ontvangen',
        'two_factor_code' => 'Authenticatiecode',
        'recovery_code' => 'Herstelcode',
    ],
    'profile_fields' => [
        'company_name' => 'Bedrijfsnaam',
        'vat_number' => 'Btw-nummer',
        'customer_type' => 'Klanttype',
    ],
    'profile_field_options' => [
        'customer_type' => [
            'private' => 'Particuliere klant',
            'business' => 'Zakelijke klant',
        ],
    ],
    'two_factor' => [
        'enabled' => 'Tweestapsauthenticatie is voorbereid. Bevestig dit met een code uit je authenticator-app.',
        'confirmed' => 'Tweestapsauthenticatie is ingeschakeld.',
        'disabled' => 'Tweestapsauthenticatie is uitgeschakeld.',
        'invalid_code' => 'De opgegeven tweestapscode is ongeldig.',
        'required' => 'Schakel tweestapsauthenticatie in voordat je verdergaat.',
    ],
    'sessions' => [
        'other_devices_signed_out' => '{0} Er zijn geen andere actieve sessies gevonden.|{1} Een ander apparaat is afgemeld.|[2,*] :count andere apparaten zijn afgemeld.',
        'revoked' => 'Deze sessie is afgemeld. Log opnieuw in.',
    ],
    'mail' => [
        'reset_password' => [
            'subject' => 'Stel je wachtwoord opnieuw in',
            'intro' => 'Je ontvangt deze e-mail omdat we een wachtwoordreset voor je account hebben ontvangen.',
            'action' => 'Wachtwoord opnieuw instellen',
            'outro' => 'Als je geen wachtwoordreset hebt aangevraagd, hoef je niets te doen.',
        ],
        'verify_email' => [
            'subject' => 'Bevestig je e-mailadres',
            'intro' => 'Klik op de knop hieronder om je e-mailadres te bevestigen.',
            'action' => 'E-mailadres bevestigen',
            'outro' => 'Als je geen account hebt aangemaakt, hoef je niets te doen.',
        ],
    ],
];
