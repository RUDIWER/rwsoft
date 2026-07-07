<?php

namespace App\Support\Cms;

class CmsSystemMailRegistry
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        return [
            'site_user.verify_email' => [
                'label' => 'Verify email address',
                'context_key' => 'public_site.auth_email',
                'template_key' => 'auth_action',
                'defaults' => [
                    'subject' => 'Verify your email address',
                    'preheader' => 'Confirm your email address to activate your account.',
                    'content_blocks' => [
                        'heading' => ['text' => 'Verify your email address'],
                        'intro' => ['text' => 'Please click the button below to verify your email address.'],
                        'action' => ['label' => 'Verify email address'],
                        'outro' => ['text' => 'If you did not create an account, no further action is required.'],
                    ],
                ],
                'localized_defaults' => [
                    'nl' => [
                        'subject' => 'Bevestig je e-mailadres',
                        'preheader' => 'Bevestig je e-mailadres om je account te activeren.',
                        'content_blocks' => [
                            'heading' => ['text' => 'Bevestig je e-mailadres'],
                            'intro' => ['text' => 'Klik op de knop hieronder om je e-mailadres te bevestigen.'],
                            'action' => ['label' => 'E-mailadres bevestigen'],
                            'outro' => ['text' => 'Als je geen account hebt aangemaakt, hoef je niets te doen.'],
                        ],
                    ],
                    'fr' => [
                        'subject' => 'Confirmez votre adresse e-mail',
                        'preheader' => 'Confirmez votre adresse e-mail pour activer votre compte.',
                        'content_blocks' => [
                            'heading' => ['text' => 'Confirmez votre adresse e-mail'],
                            'intro' => ['text' => 'Cliquez sur le bouton ci-dessous pour confirmer votre adresse e-mail.'],
                            'action' => ['label' => 'Confirmer l’adresse e-mail'],
                            'outro' => ['text' => 'Si vous n’avez pas créé de compte, aucune action n’est nécessaire.'],
                        ],
                    ],
                ],
            ],
            'site_user.reset_password' => [
                'label' => 'Reset password',
                'context_key' => 'public_site.auth_email',
                'template_key' => 'reset_password',
                'defaults' => [
                    'subject' => 'Reset your password',
                    'preheader' => 'Use the secure link to choose a new password.',
                    'content_blocks' => [
                        'heading' => ['text' => 'Reset your password'],
                        'intro' => ['text' => 'We received a request to reset your password. Click the button below to choose a new password.'],
                        'action' => ['label' => 'Reset password'],
                        'outro' => ['text' => 'If you did not request a password reset, you can ignore this email.'],
                    ],
                ],
                'localized_defaults' => [
                    'nl' => [
                        'subject' => 'Stel je wachtwoord opnieuw in',
                        'preheader' => 'Gebruik de beveiligde link om een nieuw wachtwoord te kiezen.',
                        'content_blocks' => [
                            'heading' => ['text' => 'Stel je wachtwoord opnieuw in'],
                            'intro' => ['text' => 'We hebben een aanvraag ontvangen om je wachtwoord opnieuw in te stellen. Klik op de knop hieronder om een nieuw wachtwoord te kiezen.'],
                            'action' => ['label' => 'Wachtwoord opnieuw instellen'],
                            'outro' => ['text' => 'Als je geen wachtwoordreset hebt aangevraagd, mag je deze e-mail negeren.'],
                        ],
                    ],
                    'fr' => [
                        'subject' => 'Réinitialisez votre mot de passe',
                        'preheader' => 'Utilisez le lien sécurisé pour choisir un nouveau mot de passe.',
                        'content_blocks' => [
                            'heading' => ['text' => 'Réinitialisez votre mot de passe'],
                            'intro' => ['text' => 'Nous avons reçu une demande de réinitialisation de votre mot de passe. Cliquez sur le bouton ci-dessous pour choisir un nouveau mot de passe.'],
                            'action' => ['label' => 'Réinitialiser le mot de passe'],
                            'outro' => ['text' => 'Si vous n’avez pas demandé de réinitialisation, vous pouvez ignorer cet e-mail.'],
                        ],
                    ],
                ],
            ],
            'cms_form.admin_notification' => [
                'label' => 'Form submission notification',
                'context_key' => 'cms.form_submission.email',
                'template_key' => 'form_notification',
                'defaults' => [
                    'subject' => 'New form submission: {{ form.title }}',
                    'preheader' => 'A visitor submitted a form on {{ site.name }}.',
                    'content_blocks' => [
                        'heading' => ['text' => 'New form submission'],
                        'intro' => ['text' => 'A visitor submitted the form "{{ form.title }}".'],
                        'answers' => [],
                        'outro' => ['text' => 'Open the admin panel to review and process this submission.'],
                    ],
                ],
                'localized_defaults' => [
                    'nl' => [
                        'subject' => 'Nieuwe formulierinzending: {{ form.title }}',
                        'preheader' => 'Een bezoeker heeft een formulier ingediend op {{ site.name }}.',
                        'content_blocks' => [
                            'heading' => ['text' => 'Nieuwe formulierinzending'],
                            'intro' => ['text' => 'Een bezoeker heeft het formulier "{{ form.title }}" ingediend.'],
                            'outro' => ['text' => 'Open het adminpaneel om deze inzending te bekijken en te verwerken.'],
                        ],
                    ],
                    'fr' => [
                        'subject' => 'Nouvelle soumission de formulaire : {{ form.title }}',
                        'preheader' => 'Un visiteur a soumis un formulaire sur {{ site.name }}.',
                        'content_blocks' => [
                            'heading' => ['text' => 'Nouvelle soumission de formulaire'],
                            'intro' => ['text' => 'Un visiteur a soumis le formulaire « {{ form.title }} ».'],
                            'outro' => ['text' => 'Ouvrez le panneau d’administration pour consulter et traiter cette soumission.'],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $systemKey): ?array
    {
        return $this->all()[$systemKey] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function defaults(string $systemKey, string $locale): array
    {
        $definition = $this->get($systemKey);

        if (! is_array($definition)) {
            return [];
        }

        $defaults = (array) ($definition['defaults'] ?? []);
        $localizedDefaults = (array) ($definition['localized_defaults'][$locale] ?? []);

        return array_replace_recursive($defaults, $localizedDefaults);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function templates(): array
    {
        return [
            'auth_action' => [
                'name' => 'Authentication action email',
                'description' => 'System email layout with intro text, secure action button and footer text.',
                'context_key' => 'public_site.auth_email',
                'body_blocks' => [
                    ['key' => 'company_logo', 'type' => 'company_logo', 'label' => 'Company logo', 'required' => false],
                    ['key' => 'heading', 'type' => 'heading', 'label' => 'Heading', 'required' => true],
                    ['key' => 'intro', 'type' => 'text', 'label' => 'Intro text', 'required' => true],
                    ['key' => 'action', 'type' => 'button', 'label' => 'Action button', 'required' => true, 'url_source' => 'action.url'],
                    ['key' => 'outro', 'type' => 'text', 'label' => 'Outro text', 'required' => false],
                ],
            ],
            'reset_password' => [
                'name' => 'Reset password email',
                'description' => 'System email layout for password reset links.',
                'context_key' => 'public_site.auth_email',
                'body_blocks' => [
                    ['key' => 'company_logo', 'type' => 'company_logo', 'label' => 'Company logo', 'required' => false],
                    ['key' => 'heading', 'type' => 'heading', 'label' => 'Heading', 'required' => true],
                    ['key' => 'intro', 'type' => 'text', 'label' => 'Intro text', 'required' => true],
                    ['key' => 'action', 'type' => 'button', 'label' => 'Reset password button', 'required' => true, 'url_source' => 'action.url'],
                    ['key' => 'outro', 'type' => 'text', 'label' => 'Outro text', 'required' => false],
                ],
            ],
            'form_notification' => [
                'name' => 'Form notification email',
                'description' => 'System email layout for CMS form submission notifications.',
                'context_key' => 'cms.form_submission.email',
                'body_blocks' => [
                    ['key' => 'company_logo', 'type' => 'company_logo', 'label' => 'Company logo', 'required' => false],
                    ['key' => 'heading', 'type' => 'heading', 'label' => 'Heading', 'required' => true],
                    ['key' => 'intro', 'type' => 'text', 'label' => 'Intro text', 'required' => true],
                    ['key' => 'answers', 'type' => 'form_answers', 'label' => 'Form answers', 'required' => true],
                    ['key' => 'outro', 'type' => 'text', 'label' => 'Outro text', 'required' => false],
                ],
            ],
        ];
    }
}
