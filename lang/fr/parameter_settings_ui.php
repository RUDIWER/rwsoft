<?php

return [
    'meta' => [
        'page_title' => 'Parametres IA',
    ],
    'page' => [
        'title' => 'Parametres IA',
        'subtitle' => 'Gerez les parametres IA generaux pour les actions de traduction. Cet ecran est extensible pour des fournisseurs et options supplementaires.',
    ],
    'actions' => [
        'back' => 'Retour',
        'save' => 'Enregistrer',
    ],
    'translation_ai' => [
        'title' => 'IA de traduction',
        'subtitle' => 'Choisissez le fournisseur, le modele et une cle API optionnelle.',
        'provider' => 'Fournisseur',
        'model' => 'Modele',
        'model_placeholder' => 'Nom du modele',
        'api_key' => 'Cle API (optionnelle)',
        'api_key_mask' => 'Cle API actuelle',
        'new_api_key' => 'Nouvelle cle API (optionnelle)',
        'api_key_placeholder' => 'Laisser vide pour utiliser la cle config/.env',
        'api_key_help' => 'Laisser vide pour conserver la cle stockee existante.',
        'clear_api_key' => 'Supprimer la cle API stockee',
        'api_key_present' => 'Une cle API chiffree est actuellement stockee.',
        'api_key_absent' => 'Aucune cle API stockee actuellement.',
        'fill_limit_default' => 'Taille de lot IA par defaut',
        'fill_limit_max' => 'Taille de lot IA maximale',
    ],
    'admin_locale' => [
        'title' => 'Environnement admin',
        'subtitle' => 'Choisissez la langue par defaut pour cet environnement admin tenant.',
        'default_locale' => 'Langue admin par defaut',
        'help' => 'Les utilisateurs peuvent remplacer cette langue par site via leur propre langue admin.',
    ],
    'feedback' => [
        'save_success' => 'Parametres IA enregistres.',
        'save_failed' => 'Echec de lenregistrement des parametres IA.',
    ],
];
