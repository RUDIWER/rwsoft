<?php

return array_replace_recursive(require __DIR__.'/../en/query_builder_ui.php', [
    'meta' => [
        'page_title' => 'Constructor de consultas',
    ],
    'page' => [
        'title' => 'Constructor de consultas',
        'subtitle' => 'Gestiona consultas SQL para tablas, informes, exportaciones y graficos.',
    ],
    'actions' => [
        'back' => 'Atras',
        'new' => 'Nuevo',
        'run' => 'Ejecutar',
        'save' => 'Guardar',
        'more' => 'Mas acciones',
        'delete' => 'Eliminar',
        'update' => 'Modificar',
    ],
    'form' => [
        'meta' => [
            'create_title' => 'Nueva consulta',
            'edit_prefix' => 'Consulta',
        ],
        'page' => [
            'create_title' => 'Nueva consulta',
            'edit_title' => 'Editar consulta',
            'subtitle' => 'Gestiona la construccion de consultas y la configuracion de salida.',
        ],
        'tabs' => [
            'description' => 'Descripcion',
            'query' => 'Consulta',
            'selections' => 'Selecciones',
            'output' => 'Salida',
        ],
        'sections' => [
            'basic' => 'Basico',
            'query_mode' => 'Modo de consulta',
            'output_mode' => 'Modo de salida',
            'menu_link' => 'Vinculo de menu',
            'table_settings' => 'Configuracion de tabla',
            'excel_settings' => 'Configuracion de Excel',
            'report_settings' => 'Configuracion de informe',
            'chart_settings' => 'Configuracion de grafico',
            'selections' => 'Selecciones / variables',
        ],
        'query' => [
            'sql_card_title' => 'Consulta SQL',
            'inspect' => 'Inspeccionar SQL',
            'import_bindings' => 'Importar bindings',
            'sql_label' => 'SQL *',
            'sql_placeholder' => 'Escribe aqui tu consulta SQL...',
            'inspect_done' => 'Inspeccion completada.',
            'found_bindings' => 'Bindings detectados:',
        ],
        'delete' => [
            'title' => 'Eliminar consulta',
            'subtitle' => 'Esta accion elimina la consulta de forma definitiva cuando no hay referencias activas.',
            'hint' => 'Primero comprobamos si la consulta aun se usa en menus, permisos o pantallas.',
            'label' => 'Consulta:',
        ],
    ],
    'search' => [
        'label' => 'Buscar',
        'placeholder' => 'Descripcion o clave',
    ],
    'empty' => [
        'no_queries' => 'No se encontraron consultas.',
    ],
    'status' => [
        'label' => 'Estado:',
        'active' => 'Activo',
        'inactive' => 'Inactivo',
    ],
    'validation' => [
        'summary_title' => 'Guardar esta bloqueado',
        'summary_description' => 'Corrige los campos de abajo e intentalo de nuevo.',
    ],
]);
