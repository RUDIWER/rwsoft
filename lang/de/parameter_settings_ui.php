<?php

return [
    'meta' => [
        'page_title' => 'KI Einstellungen',
    ],
    'page' => [
        'title' => 'KI Einstellungen',
        'subtitle' => 'Verwalte allgemeine KI-Parameter fuer Uebersetzungsaktionen. Dieser Bildschirm ist fuer zusaetzliche Provider und Optionen erweiterbar.',
    ],
    'actions' => [
        'back' => 'Zurueck',
        'save' => 'Speichern',
    ],
    'translation_ai' => [
        'title' => 'Uebersetzung KI',
        'subtitle' => 'Waehle Provider, Modell und optionalen API-Schluessel.',
        'provider' => 'Provider',
        'model' => 'Modell',
        'model_placeholder' => 'Modellname',
        'api_key' => 'API-Schluessel (optional)',
        'api_key_mask' => 'Aktueller API-Schluessel',
        'new_api_key' => 'Neuer API-Schluessel (optional)',
        'api_key_placeholder' => 'Leer lassen um config/.env Schluessel zu verwenden',
        'api_key_help' => 'Leer lassen um den bestehenden gespeicherten Schluessel zu behalten.',
        'clear_api_key' => 'Gespeicherten API-Schluessel loeschen',
        'api_key_present' => 'Ein verschluesselter API-Schluessel ist aktuell gespeichert.',
        'api_key_absent' => 'Aktuell ist kein API-Schluessel gespeichert.',
        'fill_limit_default' => 'Standard KI Batch-Groesse',
        'fill_limit_max' => 'Maximale KI Batch-Groesse',
    ],
    'admin_locale' => [
        'title' => 'Admin-Umgebung',
        'subtitle' => 'Waehle die Standardsprache fuer diese Tenant-Admin-Umgebung.',
        'default_locale' => 'Standard-Admin-Sprache',
        'help' => 'Benutzer koennen diese Sprache pro Site ueber ihre eigene Admin-Sprache ueberschreiben.',
    ],
    'feedback' => [
        'save_success' => 'KI Einstellungen gespeichert.',
        'save_failed' => 'Speichern der KI Einstellungen fehlgeschlagen.',
    ],
];
