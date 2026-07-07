<?php

$translations = require __DIR__.'/../en/admin_security_ui.php';

$translations['users'] = [
    'meta_title' => 'Benutzer',
    'index_title' => 'Benutzerverwaltung',
    'index_subtitle' => 'Verwalten Sie Backoffice-Benutzer, Rollen und Zugriffseinstellungen.',
    'edit_title' => 'Benutzer bearbeiten',
    'create_title' => 'Benutzer hinzufugen',
    'form_title_new' => 'Neuer Benutzer',
    'form_subtitle' => 'Verknupfen Sie hier Rollen und verwalten Sie den Backoffice-Zugriff.',
    'name' => 'Name',
    'email' => 'E-mail',
    'password' => 'Passwort',
    'password_keep' => '(leer lassen zum Beibehalten)',
    'allowed_content_locales' => 'Erlaubte Inhaltssprachen',
    'allowed_content_locales_help' => 'Nur diese offentlichen Sprachen durfen von diesem Benutzer bearbeitet werden.',
    'no_allowed_content_locales' => 'Keine Sprachen ausgewahlt',
    'remove_allowed_content_locale' => 'Inhaltssprache entfernen',
    'roles' => 'Rollen',
    'database_access' => 'Datenbankzugriff (DB Diagram)',
    'database_view_access' => 'Datenbankinhalt ansehen',
    'database_edit_access' => 'Datensatze bearbeiten',
    'database_add_access' => 'Datensatze hinzufugen',
    'database_delete_access' => 'Datensatze loschen',
    'database_export_access' => 'Tabellen-SQL-Export ausfuhren',
    'database_sql_query_access' => 'SQL readonly ausfuhren',
    'database_sql_destructive_access' => 'SQL destruktiv ausfuhren',
    'database_full_backup_access' => 'Vollstandiges Backup ausfuhren',
];

$translations['roles'] = [
    'meta_title' => 'Rollen',
    'index_title' => 'Rollen',
    'index_subtitle' => 'Verwalten Sie Backoffice-Rollen und zugewiesene Routerechte.',
    'edit_title' => 'Rolle bearbeiten',
    'create_title' => 'Rolle hinzufugen',
    'form_title_new' => 'Neue Rolle',
    'form_subtitle' => 'Definieren Sie Benutzertypen und weisen Sie routenbasierte Rechte zu.',
    'key' => 'Schlussel',
    'name' => 'Name',
    'description' => 'Beschreibung',
    'permissions' => 'Rechte',
];

$translations['permissions'] = [
    'meta_title' => 'Rechte',
    'index_title' => 'Routerechte',
    'index_subtitle' => 'Verwalten Sie Routerechte fur Module, Aktionen und Menuzugriff.',
    'edit_title' => 'Recht bearbeiten',
    'create_title' => 'Recht hinzufugen',
    'form_title_new' => 'Neues Recht',
    'form_subtitle' => 'Verwalten Sie Routerechte fur Module und Aktionen.',
    'route_name' => 'Routenname',
    'description' => 'Beschreibung',
    'module' => 'Modul',
    'action' => 'Aktion',
    'type' => 'Typ',
    'query_id' => 'Query ID',
    'url' => 'URL',
    'menu' => 'Im Menu anzeigen',
];

$translations['columns']['id'] = 'ID';
$translations['columns']['name'] = 'Name';
$translations['columns']['email'] = 'E-mail';
$translations['columns']['roles'] = 'Rollen';
$translations['columns']['users'] = 'Benutzer';
$translations['columns']['permissions'] = 'Rechte';
$translations['columns']['key'] = 'Schlussel';
$translations['columns']['route'] = 'Route';
$translations['columns']['description'] = 'Beschreibung';
$translations['columns']['module'] = 'Modul';
$translations['columns']['type'] = 'Typ';
$translations['columns']['in_menu'] = 'Im Menu';
$translations['columns']['action'] = 'Aktion';

$translations['validation']['summary_title'] = 'Speichern ist blockiert';
$translations['validation']['summary_description'] = 'Korrigieren Sie die Felder unten und versuchen Sie es erneut.';
$translations['validation']['required'] = 'Dieses Feld ist erforderlich.';
$translations['validation']['max_chars'] = ':field ist zu lang (:current/:max).';
$translations['validation']['integer'] = 'Verwenden Sie eine ganze Zahl.';
$translations['validation']['role_key'] = 'Verwenden Sie nur Kleinbuchstaben, Zahlen, Unterstriche und Bindestriche.';
$translations['validation']['invalid_choice'] = 'Wahlen Sie einen gultigen Wert.';
$translations['validation']['email'] = 'Verwenden Sie eine gultige E-mail-Adresse.';
$translations['validation']['min_chars'] = ':field ist zu kurz (:current/:min).';

return $translations;
