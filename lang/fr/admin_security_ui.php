<?php

$translations = require __DIR__.'/../en/admin_security_ui.php';

$translations['users'] = [
    'meta_title' => 'Utilisateurs',
    'index_title' => 'Gestion des utilisateurs',
    'index_subtitle' => 'Gerez les utilisateurs backoffice, les roles et les acces.',
    'edit_title' => 'Modifier l utilisateur',
    'create_title' => 'Ajouter un utilisateur',
    'form_title_new' => 'Nouvel utilisateur',
    'form_subtitle' => 'Liez ici les roles et gerez l acces au backoffice.',
    'name' => 'Nom',
    'email' => 'E-mail',
    'password' => 'Mot de passe',
    'password_keep' => '(laisser vide pour conserver)',
    'allowed_content_locales' => 'Langues de contenu autorisees',
    'allowed_content_locales_help' => 'Seules ces langues publiques peuvent etre modifiees par cet utilisateur.',
    'no_allowed_content_locales' => 'Aucune langue selectionnee',
    'remove_allowed_content_locale' => 'Supprimer la langue de contenu',
    'roles' => 'Roles',
    'database_access' => 'Acces base de donnees (DB Diagram)',
    'database_view_access' => 'Voir le contenu de la base',
    'database_edit_access' => 'Modifier les enregistrements',
    'database_add_access' => 'Ajouter des enregistrements',
    'database_delete_access' => 'Supprimer des enregistrements',
    'database_export_access' => 'Executer export SQL de table',
    'database_sql_query_access' => 'Executer SQL readonly',
    'database_sql_destructive_access' => 'Executer SQL destructif',
    'database_full_backup_access' => 'Executer une sauvegarde complete',
];

$translations['roles'] = [
    'meta_title' => 'Roles',
    'index_title' => 'Roles',
    'index_subtitle' => 'Gerez les roles backoffice et les droits de route associes.',
    'edit_title' => 'Modifier le role',
    'create_title' => 'Ajouter un role',
    'form_title_new' => 'Nouveau role',
    'form_subtitle' => 'Definissez des types d utilisateurs et assignez des droits bases sur les routes.',
    'key' => 'Cle',
    'name' => 'Nom',
    'description' => 'Description',
    'permissions' => 'Droits',
];

$translations['permissions'] = [
    'meta_title' => 'Droits',
    'index_title' => 'Droits de route',
    'index_subtitle' => 'Gerez les droits de route pour les modules, actions et acces au menu.',
    'edit_title' => 'Modifier le droit',
    'create_title' => 'Ajouter un droit',
    'form_title_new' => 'Nouveau droit',
    'form_subtitle' => 'Gerez les droits de route pour les modules et actions.',
    'route_name' => 'Nom de route',
    'description' => 'Description',
    'module' => 'Module',
    'action' => 'Action',
    'type' => 'Type',
    'query_id' => 'Query ID',
    'url' => 'URL',
    'menu' => 'Afficher dans le menu',
];

$translations['columns']['id'] = 'ID';
$translations['columns']['name'] = 'Nom';
$translations['columns']['email'] = 'E-mail';
$translations['columns']['roles'] = 'Roles';
$translations['columns']['users'] = 'Utilisateurs';
$translations['columns']['permissions'] = 'Droits';
$translations['columns']['key'] = 'Cle';
$translations['columns']['route'] = 'Route';
$translations['columns']['description'] = 'Description';
$translations['columns']['module'] = 'Module';
$translations['columns']['type'] = 'Type';
$translations['columns']['in_menu'] = 'Dans le menu';
$translations['columns']['action'] = 'Action';

$translations['validation']['summary_title'] = 'Enregistrement bloque';
$translations['validation']['summary_description'] = 'Corrigez les champs ci-dessous et reessayez.';
$translations['validation']['required'] = 'Ce champ est obligatoire.';
$translations['validation']['max_chars'] = ':field est trop long (:current/:max).';
$translations['validation']['integer'] = 'Utilisez un nombre entier.';
$translations['validation']['role_key'] = 'Utilisez uniquement des lettres minuscules, chiffres, underscores et tirets.';
$translations['validation']['invalid_choice'] = 'Choisissez une valeur valide.';
$translations['validation']['email'] = 'Utilisez une adresse e-mail valide.';
$translations['validation']['min_chars'] = ':field est trop court (:current/:min).';

return $translations;
