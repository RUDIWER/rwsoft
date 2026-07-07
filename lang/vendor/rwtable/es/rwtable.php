<?php

return array (
  'backend' => 
  array (
    'messages' => 
    array (
      'chart_deleted' => 'Gráfico eliminado con éxito.',
      'chart_saved' => 'Gráfico guardado con éxito.',
      'export_deleted' => 'Configuración de exportación eliminada con éxito.',
      'export_saved' => 'Configuración de exportación guardada con éxito.',
      'extra_field_not_editable' => 'El campo \':field\' no es editable.',
      'field_not_editable' => 'El campo no es editable.',
      'field_required' => 'El campo es obligatorio.',
      'model_rules_missing' => 'Faltan las reglas de validación del modelo.',
      'validation_rules_missing_for_field' => 'Faltan las reglas de validación para el campo \':field\'.',
      'validation_rules_required' => 'Las reglas de validación son obligatorias.',
      'validation_rules_required_for_extra_field' => 'Las reglas de validación para el campo extra \':field\' son obligatorias.',
      'validation_type_required' => 'El tipo de validación es obligatorio.',
    ),
  ),
  'vue' => 
  array (
    'actions' => 
    array (
      'back' => 'Volver',
      'cancel_new_row' => 'Cancelar nueva fila',
      'clear' => 'Limpiar',
      'close' => 'Cerrar',
      'delete' => 'Eliminar',
      'edit' => 'Editar',
      'insert_above' => 'Insertar arriba',
      'new' => 'Nuevo',
      'new_row' => 'Nueva fila',
      'save' => 'Guardar',
      'view' => 'Ver',
    ),
    'autocomplete' => 
    array (
      'more' => 'más',
      'no_results' => 'Sin resultados',
      'use_custom_value' => 'Usar valor personalizado:',
    ),
    'charts' => 
    array (
      'actions' => 
      array (
        'print_pdf' => 'Imprimir PDF',
      ),
      'aggregate_items' => 
      array (
        'avg' => 'Promedio (avg)',
        'count' => 'Recuento (count)',
        'max' => 'Máximo (max)',
        'min' => 'Mínimo (min)',
        'sum' => 'Suma (sum)',
      ),
      'dialog' => 
      array (
        'title_edit' => 'Editar Gráfico',
        'title_list' => 'Gestión de Gráficos',
        'title_new' => 'Nuevo Gráfico',
        'title_view' => 'Ver Gráfico',
      ),
      'fields' => 
      array (
        'aggregate' => 'Cálculo (agregación)',
        'allow_type_change' => 'Permitir cambio de tipo de gráfico en el visor',
        'limit' => 'Top N (1-500)',
        'metric_field' => 'Campo métrico',
        'no_series' => '-- Sin series --',
        'orientation' => 'Orientación',
        'series_field_optional' => 'Campo de serie (opcional)',
        'show_legend' => 'Mostrar leyenda',
        'sort' => 'Ordenar',
        'stacked' => 'Apilado',
        'type' => 'Tipo de gráfico',
        'viewer_type' => 'Tipo de gráfico en el visor',
        'x_field' => 'Campo X',
      ),
      'manage_title' => 'Gestión de Gráficos',
      'messages' => 
      array (
        'delete_failed' => 'Falló la eliminación de la configuración del gráfico.',
        'load_failed' => 'No se pudieron cargar los gráficos guardados para esta tabla.',
        'loading_data' => 'Cargando datos del gráfico...',
        'no_renderable_data' => 'No se encontraron datos de gráfico renderizables para la configuración actual.',
        'none_saved' => 'No se encontraron gráficos guardados.',
        'pdf_failed' => 'Falló la impresión en PDF del gráfico.',
        'pdf_not_available' => 'No se puede generar el PDF porque el gráfico aún no está disponible.',
        'render_failed' => 'No se pudo renderizar el gráfico con la configuración actual.',
        'save_failed' => 'Falló al guardar la configuración del gráfico.',
        'saved' => 'Configuración del gráfico guardada.',
        'source_load_failed' => 'No se pudieron cargar los datos de origen del gráfico.',
        'webgl_unsupported' => 'WebGL no es compatible con este navegador o GPU. Elija un tipo de gráfico que no sea WebGL.',
      ),
      'orientation_items' => 
      array (
        'horizontal' => 'Horizontal',
        'vertical' => 'Vertical',
      ),
      'pdf' => 
      array (
        'default_filename' => 'gráfico',
        'default_title' => 'Gráfico',
        'image_alt' => 'Exportación de gráfico',
      ),
      'placeholders' => 
      array (
        'description' => 'Por ejemplo: Inscripciones por año escolar',
      ),
      'series' => 
      array (
        'total' => 'Total',
      ),
      'sort_direction_items' => 
      array (
        'asc' => 'Ascendente',
        'desc' => 'Descendente',
      ),
      'type_items' => 
      array (
        'bar' => 'Barra',
        'bar3d' => 'Barra 3D',
        'bar3d_webgl' => 'Barra 3D (WebGL)',
        'doughnut' => 'Dona',
        'line' => 'Línea',
        'line3d' => 'Línea 3D',
        'line3d_webgl' => 'Línea 3D (WebGL)',
        'pie' => 'Circular',
      ),
      'validation' => 
      array (
        'minimum_required' => 'Rellene al menos Descripción, Campo X y, si es necesario, un Campo métrico.',
      ),
    ),
    'columns' => 
    array (
      'action' => 'Acción',
      'active' => 'Activo',
      'article_description' => 'Artículo / Descripción',
      'created_at' => 'Creado el',
      'description' => 'Descripción',
      'id' => 'ID',
      'in_menu' => 'En menú',
      'labels' => 'Etiquetas',
      'module' => 'Módulo',
      'notes' => 'Notas',
      'order' => 'Orden',
      'owner' => 'Propietario',
      'priority' => 'Prioridad',
      'product_id' => 'ID de Producto',
      'route' => 'Ruta',
      'status' => 'Estado',
      'title' => 'Título',
    ),
    'common' => 
    array (
      'choose_field' => '-- Elegir campo --',
      'dash' => '-',
      'description_title' => 'Descripción / Título',
      'no' => 'No',
      'yes' => 'Sí',
    ),
    'excel' => 
    array (
      'actions' => 
      array (
        'download_direct' => 'Descarga directa',
      ),
      'dialog' => 
      array (
        'title_button' => 'Exportar a Excel',
        'title_edit' => 'Editar Exportación',
        'title_list' => 'Gestión de Exportación a Excel',
        'title_new' => 'Nueva Exportación',
      ),
      'fields' => 
      array (
        'select_sort_columns' => 'Seleccionar y ordenar columnas',
      ),
      'messages' => 
      array (
        'delete_failed' => 'Falló la eliminación de la exportación.',
        'download_failed' => 'Se produjo un error al generar la exportación de Excel.',
        'load_failed' => 'No se pudieron cargar las exportaciones guardadas para esta tabla.',
        'no_columns_selected' => 'Seleccione al menos una columna para exportar.',
        'no_data' => 'No se encontraron datos para exportar.',
        'none_saved' => 'No se encontraron exportaciones guardadas.',
        'save_failed' => 'Falló al guardar la configuración de exportación.',
        'saved' => 'Configuración de exportación guardada.',
      ),
      'placeholders' => 
      array (
        'description' => 'Por ejemplo: Resumen de registros activos',
      ),
    ),
    'filters' => 
    array (
      'aria' => 
      array (
        'filter_column' => 'Filtrar columna :label',
        'from_date_for' => 'Filtrar desde la fecha para :label',
        'operator_for' => 'Operador de filtro para :label',
        'to_date_for' => 'Filtrar hasta la fecha para :label',
        'value_for' => 'Valor de filtro para :label',
      ),
      'choose_value' => 'Elegir valor',
      'clear_all' => 'Limpiar filtros',
      'free_text' => 'Texto libre',
      'from' => 'Desde',
      'modes' => 
      array (
        'after' => 'Después',
        'before' => 'Antes',
        'between' => 'Entre',
        'contains' => 'Contiene',
        'contains_option' => 'Contiene opción',
        'contains_option_all' => 'Contiene opción (todas las elegidas)',
        'equals' => 'Es igual a',
        'equals_option' => 'Es igual a la opción',
        'equals_option_exact' => 'Es igual a la opción (conjunto exacto)',
        'greater_than' => 'Mayor que',
        'less_than' => 'Menor que',
        'not_contains' => 'No contiene',
        'not_equals' => 'No es igual a',
      ),
      'option_value' => 'Valor de la lista',
      'to' => 'Hasta',
      'value' => 'Valor',
    ),
    'search' => 
    array (
      'all_columns' => 'Buscar en todas las columnas',
    ),
    'table' => 
    array (
      'actions' => 'Acciones',
      'aria' => 
      array (
        'edit_field' => 'Editar :label',
        'new_value_for' => 'Nuevo valor para :label',
        'select_all_visible_rows' => 'Seleccionar todas las filas visibles',
        'select_row' => 'Seleccionar fila :id',
      ),
      'column' => 
      array (
        'aria' => 
        array (
          'drag_column' => 'Arrastrar columna :label',
          'pin_column' => 'Fijar columna :label',
          'resize_column' => 'Redimensionar :label',
          'toggle_column' => 'Mostrar columna :label',
        ),
      ),
      'config' => 
      array (
        'enable_horizontal_scroll' => 'Habilitar desplazamiento horizontal',
        'height' => 'Altura de la tabla',
        'restore_default' => 'Restaurar predeterminado',
        'show_record_count' => 'Mostrar recuento de registros',
        'show_row_quantity_select' => 'Mostrar selección de cantidad de filas',
        'title' => 'Configuración',
        'use_pagination' => 'Usar paginación en lugar de desplazamiento infinito',
      ),
      'description' => 'Descripción',
      'id' => 'Id',
      'loading' => 'Cargando...',
      'manual_ordering_active' => 'Orden manual activo',
      'no_records' => 'No se encontraron registros.',
      'record_count' => 'Número de filas: :count',
      'rows_per_page' => 'Filas por página',
    ),
    'validation' => 
    array (
      'custom' => 
      array (
        'enterprise_be' => ':attribute debe ser un número de empresa belga válido (KBO/BCE).',
        'iban_be' => ':attribute debe ser un IBAN belga válido (BE + 14 dígitos).',
        'min_words' => ':attribute debe contener al menos :min palabras.',
        'phone_be' => ':attribute debe ser un número de teléfono belga válido.',
        'postcode_be' => ':attribute debe ser un código postal belga válido (1000-9999).',
        'rrn_be' => ':attribute debe ser un número de registro nacional belga válido (11 dígitos).',
      ),
      'custom_failed' => ':attribute no es válido para la regla de cliente :rule.',
      'custom_runtime_error' => 'La regla de cliente :rule no pudo ejecutarse.',
      'custom_unknown_rule' => ':attribute utiliza una regla de cliente desconocida :rule.',
      'invalid_value' => 'Valor inválido.',
      'not_saved_check_fields' => 'No guardado. Compruebe los campos marcados en rojo.',
      'not_saved_unexpected' => 'No guardado debido a un error inesperado.',
      'rules' => 
      array (
        'array' => ':attribute debe ser una lista.',
        'boolean' => ':attribute debe ser verdadero o falso.',
        'confirmed' => 'La confirmación de :attribute no coincide.',
        'email' => ':attribute debe ser una dirección de correo electrónico válida.',
        'in' => ':attribute debe ser uno de estos valores: :values.',
        'integer' => ':attribute debe ser un número entero.',
        'max' => 
        array (
          'array' => ':attribute no puede contener más de :max elementos.',
          'numeric' => ':attribute no debe ser mayor que :max.',
          'string' => ':attribute no puede tener más de :max caracteres.',
        ),
        'min' => 
        array (
          'array' => ':attribute debe contener al menos :min elementos.',
          'numeric' => ':attribute debe ser al menos :min.',
          'string' => ':attribute debe contener al menos :min caracteres.',
        ),
        'not_regex' => 'El formato de :attribute no es válido.',
        'numeric' => ':attribute debe ser un número.',
        'regex' => 'El formato de :attribute no es válido.',
        'required' => ':attribute es obligatorio.',
        'same' => ':attribute debe ser igual a :other.',
        'string' => ':attribute debe ser texto.',
      ),
      'this_field' => 'Este campo',
    ),
  ),
);
