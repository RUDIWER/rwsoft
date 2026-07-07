<?php

return array_replace_recursive(require __DIR__.'/../en/query_builder_ui.php', [
    'meta' => [
        'page_title' => 'Query Builder',
    ],
    'page' => [
        'title' => 'Query Builder',
        'subtitle' => 'SQL-Abfragen fur Tabellen, Berichte, Exporte und Diagramme verwalten.',
    ],
    'actions' => [
        'back' => 'Zuruck',
        'new' => 'Neu',
        'run' => 'Ausfuhren',
        'save' => 'Speichern',
        'more' => 'Mehr Aktionen',
        'delete' => 'Loschen',
        'update' => 'Andern',
    ],
    'form' => [
        'meta' => [
            'create_title' => 'Neue Abfrage',
            'edit_prefix' => 'Abfrage',
        ],
        'page' => [
            'create_title' => 'Neue Abfrage',
            'edit_title' => 'Abfrage bearbeiten',
            'subtitle' => 'Abfrageaufbau und Ausgabeeinstellungen verwalten.',
        ],
        'tabs' => [
            'description' => 'Beschreibung',
            'query' => 'Abfrage',
            'selections' => 'Auswahlen',
            'output' => 'Ausgabe',
        ],
        'sections' => [
            'basic' => 'Basis',
            'query_mode' => 'Abfragemodus',
            'output_mode' => 'Ausgabemodus',
            'menu_link' => 'Menuverknupfung',
            'table_settings' => 'Tabelleneinstellungen',
            'excel_settings' => 'Excel-Einstellungen',
            'report_settings' => 'Berichtseinstellungen',
            'chart_settings' => 'Diagrammeinstellungen',
            'selections' => 'Auswahlen / Variablen',
        ],
        'query' => [
            'sql_card_title' => 'SQL-Abfrage',
            'inspect' => 'SQL prufen',
            'import_bindings' => 'Bindings ubernehmen',
            'sql_label' => 'SQL *',
            'sql_placeholder' => 'Schreiben Sie hier Ihre SQL-Abfrage...',
            'inspect_done' => 'Prufung abgeschlossen.',
            'found_bindings' => 'Gefundene Bindings:',
        ],
        'delete' => [
            'title' => 'Abfrage loschen',
            'subtitle' => 'Diese Aktion loscht die Abfrage dauerhaft, wenn keine aktiven Referenzen vorhanden sind.',
            'hint' => 'Wir prufen zuerst, ob die Abfrage noch in Menus, Berechtigungen oder Screens verwendet wird.',
            'label' => 'Abfrage:',
        ],
    ],
    'search' => [
        'label' => 'Suchen',
        'placeholder' => 'Beschreibung oder Key',
    ],
    'empty' => [
        'no_queries' => 'Keine Abfragen gefunden.',
    ],
    'status' => [
        'label' => 'Status:',
        'active' => 'Aktiv',
        'inactive' => 'Inaktiv',
    ],
    'validation' => [
        'summary_title' => 'Speichern ist blockiert',
        'summary_description' => 'Korrigieren Sie die Felder unten und versuchen Sie es erneut.',
    ],
]);
