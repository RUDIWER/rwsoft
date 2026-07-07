<?php

return array_replace_recursive(require __DIR__.'/../en/query_builder_ui.php', [
    'meta' => [
        'page_title' => 'Generateur de requetes',
    ],
    'page' => [
        'title' => 'Generateur de requetes',
        'subtitle' => 'Gerez les requetes SQL pour les tables, rapports, exports et graphiques.',
    ],
    'actions' => [
        'back' => 'Retour',
        'new' => 'Nouveau',
        'run' => 'Executer',
        'save' => 'Enregistrer',
        'more' => 'Plus dactions',
        'delete' => 'Supprimer',
        'update' => 'Modifier',
    ],
    'form' => [
        'meta' => [
            'create_title' => 'Nouvelle requete',
            'edit_prefix' => 'Requete',
        ],
        'page' => [
            'create_title' => 'Nouvelle requete',
            'edit_title' => 'Modifier la requete',
            'subtitle' => 'Gerez la construction des requetes et les parametres de sortie.',
        ],
        'tabs' => [
            'description' => 'Description',
            'query' => 'Requete',
            'selections' => 'Selections',
            'output' => 'Sortie',
        ],
        'sections' => [
            'basic' => 'Base',
            'query_mode' => 'Mode requete',
            'output_mode' => 'Mode sortie',
            'menu_link' => 'Lien menu',
            'table_settings' => 'Parametres tableau',
            'excel_settings' => 'Parametres Excel',
            'report_settings' => 'Parametres rapport',
            'chart_settings' => 'Parametres graphique',
            'selections' => 'Selections / variables',
        ],
        'query' => [
            'sql_card_title' => 'Requete SQL',
            'inspect' => 'Verifier SQL',
            'import_bindings' => 'Importer les liaisons',
            'sql_label' => 'SQL *',
            'sql_placeholder' => 'Ecrivez votre requete SQL ici...',
            'inspect_done' => 'Verification executee.',
            'found_bindings' => 'Liaisons detectees :',
        ],
        'delete' => [
            'title' => 'Supprimer la requete',
            'subtitle' => 'Cette action supprime definitivement la requete sil ny a aucune reference active.',
            'hint' => 'Nous verifions dabord si la requete est encore utilisee dans les menus, permissions ou ecrans.',
            'label' => 'Requete :',
        ],
    ],
    'search' => [
        'label' => 'Rechercher',
        'placeholder' => 'Description ou cle',
    ],
    'empty' => [
        'no_queries' => 'Aucune requete trouvee.',
    ],
    'status' => [
        'label' => 'Statut:',
        'active' => 'Actif',
        'inactive' => 'Inactif',
    ],
    'validation' => [
        'summary_title' => 'L enregistrement est bloque',
        'summary_description' => 'Corrigez les champs ci-dessous et reessayez.',
    ],
]);
