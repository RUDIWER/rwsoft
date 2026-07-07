<?php

$translations = require __DIR__.'/../en/admin_security_ui.php';

$translations['users'] = [
    'meta_title' => 'Usuarios',
    'index_title' => 'Gestion de usuarios',
    'index_subtitle' => 'Administre usuarios de backoffice, roles y accesos.',
    'edit_title' => 'Editar usuario',
    'create_title' => 'Agregar usuario',
    'form_title_new' => 'Nuevo usuario',
    'form_subtitle' => 'Asigne aqui roles y gestione el acceso al backoffice.',
    'name' => 'Nombre',
    'email' => 'E-mail',
    'password' => 'Contrasena',
    'password_keep' => '(dejar vacio para conservar)',
    'allowed_content_locales' => 'Idiomas de contenido permitidos',
    'allowed_content_locales_help' => 'Solo estos idiomas publicos pueden ser editados por este usuario.',
    'no_allowed_content_locales' => 'No hay idiomas seleccionados',
    'remove_allowed_content_locale' => 'Eliminar idioma de contenido',
    'roles' => 'Roles',
    'database_access' => 'Acceso a base de datos (DB Diagram)',
    'database_view_access' => 'Ver contenido de base de datos',
    'database_edit_access' => 'Editar registros',
    'database_add_access' => 'Agregar registros',
    'database_delete_access' => 'Eliminar registros',
    'database_export_access' => 'Ejecutar export SQL de tabla',
    'database_sql_query_access' => 'Ejecutar SQL readonly',
    'database_sql_destructive_access' => 'Ejecutar SQL destructivo',
    'database_full_backup_access' => 'Ejecutar backup completo',
];

$translations['roles'] = [
    'meta_title' => 'Roles',
    'index_title' => 'Roles',
    'index_subtitle' => 'Administre roles de backoffice y derechos de ruta asignados.',
    'edit_title' => 'Editar rol',
    'create_title' => 'Agregar rol',
    'form_title_new' => 'Nuevo rol',
    'form_subtitle' => 'Defina tipos de usuario y asigne derechos basados en rutas.',
    'key' => 'Clave',
    'name' => 'Nombre',
    'description' => 'Descripcion',
    'permissions' => 'Derechos',
];

$translations['permissions'] = [
    'meta_title' => 'Derechos',
    'index_title' => 'Derechos de ruta',
    'index_subtitle' => 'Administre derechos de ruta para modulos, acciones y acceso al menu.',
    'edit_title' => 'Editar derecho',
    'create_title' => 'Agregar derecho',
    'form_title_new' => 'Nuevo derecho',
    'form_subtitle' => 'Administre derechos de ruta para modulos y acciones.',
    'route_name' => 'Nombre de ruta',
    'description' => 'Descripcion',
    'module' => 'Modulo',
    'action' => 'Accion',
    'type' => 'Tipo',
    'query_id' => 'Query ID',
    'url' => 'URL',
    'menu' => 'Mostrar en menu',
];

$translations['columns']['id'] = 'ID';
$translations['columns']['name'] = 'Nombre';
$translations['columns']['email'] = 'E-mail';
$translations['columns']['roles'] = 'Roles';
$translations['columns']['users'] = 'Usuarios';
$translations['columns']['permissions'] = 'Derechos';
$translations['columns']['key'] = 'Clave';
$translations['columns']['route'] = 'Ruta';
$translations['columns']['description'] = 'Descripcion';
$translations['columns']['module'] = 'Modulo';
$translations['columns']['type'] = 'Tipo';
$translations['columns']['in_menu'] = 'En menu';
$translations['columns']['action'] = 'Accion';

$translations['validation']['summary_title'] = 'Guardar esta bloqueado';
$translations['validation']['summary_description'] = 'Corrija los campos de abajo e intente de nuevo.';
$translations['validation']['required'] = 'Este campo es obligatorio.';
$translations['validation']['max_chars'] = ':field es demasiado largo (:current/:max).';
$translations['validation']['integer'] = 'Use un numero entero.';
$translations['validation']['role_key'] = 'Use solo letras minusculas, numeros, guiones y guiones bajos.';
$translations['validation']['invalid_choice'] = 'Elija un valor valido.';
$translations['validation']['email'] = 'Use una direccion de correo valida.';
$translations['validation']['min_chars'] = ':field es demasiado corto (:current/:min).';

return $translations;
