<?php

return [
    'actions' => [
        'back' => 'Volver',
        'save' => 'Guardar',
    ],
    'feedback' => [
        'save_failed' => 'No se pudieron guardar los ajustes de IA.',
        'save_success' => 'Ajustes de IA guardados.',
    ],
    'meta' => [
        'page_title' => 'Ajustes de IA',
    ],
    'page' => [
        'subtitle' => 'Gestiona los parámetros generales de IA para las acciones de traducción. Esta pantalla es extensible para proveedores y opciones adicionales.',
        'title' => 'Ajustes de IA',
    ],
    'translation_ai' => [
        'api_key' => 'Clave API (opcional)',
        'api_key_absent' => 'Actualmente no hay una clave API guardada.',
        'api_key_help' => 'Deja en blanco para mantener la clave guardada existente.',
        'api_key_mask' => 'Clave API actual',
        'api_key_placeholder' => 'Dejar en blanco usa la clave de config/.env',
        'api_key_present' => 'Actualmente hay una clave API encriptada guardada.',
        'clear_api_key' => 'Borrar clave API guardada',
        'fill_limit_default' => 'Tamaño de lote predeterminado de IA',
        'fill_limit_max' => 'Tamaño máximo de lote de IA',
        'model' => 'Modelo',
        'model_placeholder' => 'Nombre del modelo',
        'new_api_key' => 'Nueva clave API (opcional)',
        'provider' => 'Proveedor',
        'subtitle' => 'Elige proveedor, modelo y una clave API opcional.',
        'title' => 'Traducción de IA',
    ],
    'admin_locale' => [
        'title' => 'Entorno admin',
        'subtitle' => 'Elige el idioma por defecto para este entorno admin tenant.',
        'default_locale' => 'Idioma admin por defecto',
        'help' => 'Los usuarios pueden sobrescribir este idioma por sitio mediante su propio idioma admin.',
    ],
];
