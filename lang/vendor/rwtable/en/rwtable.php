<?php

return array (
  'backend' => 
  array (
    'messages' => 
    array (
      'chart_deleted' => 'Chart deleted successfully.',
      'chart_saved' => 'Chart saved successfully.',
      'export_deleted' => 'Export configuration deleted successfully.',
      'export_saved' => 'Export configuration saved successfully.',
      'extra_field_not_editable' => 'Field \':field\' is not editable.',
      'field_not_editable' => 'Field is not editable.',
      'field_required' => 'Field is required.',
      'model_rules_missing' => 'Model validation rules are missing.',
      'validation_rules_missing_for_field' => 'Validation rules for field \':field\' are missing.',
      'validation_rules_required' => 'Validation rules are required.',
      'validation_rules_required_for_extra_field' => 'Validation rules for extra field \':field\' are required.',
      'validation_type_required' => 'Validation type is required.',
    ),
  ),
  'vue' => 
  array (
    'actions' => 
    array (
      'back' => 'Back',
      'cancel_new_row' => 'Cancel new row',
      'clear' => 'Clear',
      'close' => 'Close',
      'delete' => 'Delete',
      'edit' => 'Edit',
      'insert_above' => 'Insert above',
      'new' => 'New',
      'new_row' => 'New row',
      'save' => 'Save',
      'view' => 'View',
    ),
    'autocomplete' => 
    array (
      'more' => 'more',
      'no_results' => 'No results',
      'use_custom_value' => 'Use custom value:',
    ),
    'charts' => 
    array (
      'actions' => 
      array (
        'print_pdf' => 'Print PDF',
      ),
      'aggregate_items' => 
      array (
        'avg' => 'Average',
        'count' => 'Count',
        'max' => 'Maximum',
        'min' => 'Minimum',
        'sum' => 'Sum',
      ),
      'dialog' => 
      array (
        'title_edit' => 'Edit chart',
        'title_list' => 'Charts management',
        'title_new' => 'New chart',
        'title_view' => 'View chart',
      ),
      'fields' => 
      array (
        'aggregate' => 'Calculation (aggregate)',
        'allow_type_change' => 'Allow chart type change in viewer',
        'limit' => 'Top N (1-500)',
        'metric_field' => 'Metric field',
        'no_series' => '-- No series --',
        'orientation' => 'Orientation',
        'series_field_optional' => 'Series field (optional)',
        'show_legend' => 'Show legend',
        'sort' => 'Sorting',
        'stacked' => 'Stacked',
        'type' => 'Chart type',
        'viewer_type' => 'Viewer chart type',
        'x_field' => 'X field',
      ),
      'manage_title' => 'Chart management',
      'messages' => 
      array (
        'delete_failed' => 'Deleting chart configuration failed.',
        'load_failed' => 'Could not load saved charts for this table.',
        'loading_data' => 'Loading chart data...',
        'no_renderable_data' => 'No renderable chart data found for the current settings.',
        'none_saved' => 'No saved charts found.',
        'pdf_failed' => 'Printing chart PDF failed.',
        'pdf_not_available' => 'Cannot generate PDF because the chart is not yet available.',
        'render_failed' => 'Could not render the chart with the current settings.',
        'save_failed' => 'Saving chart configuration failed.',
        'saved' => 'Chart configuration saved.',
        'source_load_failed' => 'Could not load chart source data.',
        'webgl_unsupported' => 'WebGL is not supported in this browser or on this GPU. Choose a non-WebGL chart type.',
      ),
      'orientation_items' => 
      array (
        'horizontal' => 'Horizontal',
        'vertical' => 'Vertical',
      ),
      'pdf' => 
      array (
        'default_filename' => 'chart',
        'default_title' => 'Chart',
        'image_alt' => 'Chart export',
      ),
      'placeholders' => 
      array (
        'description' => 'For example: Registrations per school year',
      ),
      'series' => 
      array (
        'total' => 'Total',
      ),
      'sort_direction_items' => 
      array (
        'asc' => 'Ascending',
        'desc' => 'Descending',
      ),
      'type_items' => 
      array (
        'bar' => 'Bar',
        'bar3d' => 'Bar 3D',
        'bar3d_webgl' => 'Bar 3D (WebGL)',
        'doughnut' => 'Doughnut',
        'line' => 'Line',
        'line3d' => 'Line 3D',
        'line3d_webgl' => 'Line 3D (WebGL)',
        'pie' => 'Pie',
      ),
      'validation' => 
      array (
        'minimum_required' => 'Fill in at least Description, X field and, when needed, a Metric field.',
      ),
    ),
    'columns' => 
    array (
      'action' => 'Action',
      'active' => 'Active',
      'article_description' => 'Article / Description',
      'created_at' => 'Created at',
      'description' => 'Description',
      'id' => 'ID',
      'in_menu' => 'In menu',
      'labels' => 'Labels',
      'module' => 'Module',
      'notes' => 'Notes',
      'order' => 'Order',
      'owner' => 'Owner',
      'priority' => 'Priority',
      'product_id' => 'Product ID',
      'route' => 'Route',
      'status' => 'Status',
      'title' => 'Title',
    ),
    'common' => 
    array (
      'choose_field' => '-- Select field --',
      'dash' => '-',
      'description_title' => 'Description / Title',
      'no' => 'No',
      'yes' => 'Yes',
    ),
    'excel' => 
    array (
      'actions' => 
      array (
        'download_direct' => 'Direct download',
      ),
      'dialog' => 
      array (
        'title_button' => 'Excel export',
        'title_edit' => 'Edit export',
        'title_list' => 'Excel export management',
        'title_new' => 'New export',
      ),
      'fields' => 
      array (
        'select_sort_columns' => 'Select and sort columns',
      ),
      'messages' => 
      array (
        'delete_failed' => 'Deleting export failed.',
        'download_failed' => 'An error occurred while generating the Excel export.',
        'load_failed' => 'Could not load saved exports for this table.',
        'no_columns_selected' => 'Select at least one column for export.',
        'no_data' => 'No data found to export.',
        'none_saved' => 'No saved exports found.',
        'save_failed' => 'Saving export configuration failed.',
        'saved' => 'Export configuration saved.',
      ),
      'placeholders' => 
      array (
        'description' => 'For example: Overview of active records',
      ),
    ),
    'filters' => 
    array (
      'aria' => 
      array (
        'filter_column' => 'Filter column :label',
        'from_date_for' => 'Filter from date for :label',
        'operator_for' => 'Filter operator for :label',
        'to_date_for' => 'Filter to date for :label',
        'value_for' => 'Filter value for :label',
      ),
      'choose_value' => 'Choose value',
      'clear_all' => 'Clear filters',
      'free_text' => 'Free text',
      'from' => 'From',
      'modes' => 
      array (
        'after' => 'After',
        'before' => 'Before',
        'between' => 'Between',
        'contains' => 'Contains',
        'contains_option' => 'Contains option',
        'contains_option_all' => 'Contains option (all selected)',
        'equals' => 'Equals',
        'equals_option' => 'Equals option',
        'equals_option_exact' => 'Equals option (exact set)',
        'greater_than' => 'Greater than',
        'less_than' => 'Less than',
        'not_contains' => 'Does not contain',
        'not_equals' => 'Not equal',
      ),
      'option_value' => 'Value from list',
      'to' => 'To',
      'value' => 'Value',
    ),
    'search' => 
    array (
      'all_columns' => 'Search all columns',
    ),
    'table' => 
    array (
      'actions' => 'Actions',
      'aria' => 
      array (
        'edit_field' => 'Edit :label',
        'new_value_for' => 'New value for :label',
        'select_all_visible_rows' => 'Select all visible rows',
        'select_row' => 'Select row :id',
      ),
      'column' => 
      array (
        'aria' => 
        array (
          'drag_column' => 'Drag column :label',
          'pin_column' => 'Pin column :label',
          'resize_column' => 'Resize column :label',
          'toggle_column' => 'Show column :label',
        ),
      ),
      'config' => 
      array (
        'enable_horizontal_scroll' => 'Enable horizontal scroll',
        'height' => 'Table height',
        'restore_default' => 'Restore default',
        'show_record_count' => 'Show record count',
        'show_row_quantity_select' => 'Show row quantity selector',
        'title' => 'Configuration',
        'use_pagination' => 'Use pagination instead of infinite scroll',
      ),
      'description' => 'Description',
      'id' => 'ID',
      'loading' => 'Loading...',
      'manual_ordering_active' => 'Manual ordering active',
      'no_records' => 'No records found.',
      'record_count' => 'Rows: :count',
      'rows_per_page' => 'Rows per page',
    ),
    'validation' => 
    array (
      'custom' => 
      array (
        'enterprise_be' => ':attribute must be a valid Belgian enterprise number (KBO/BCE).',
        'iban_be' => ':attribute must be a valid Belgian IBAN (BE + 14 digits).',
        'min_words' => ':attribute must contain at least :min words.',
        'phone_be' => ':attribute must be a valid Belgian phone number.',
        'postcode_be' => ':attribute must be a valid Belgian postcode (1000-9999).',
        'rrn_be' => ':attribute must be a valid Belgian national register number (11 digits).',
      ),
      'custom_failed' => ':attribute is invalid for client rule :rule.',
      'custom_runtime_error' => 'Client rule :rule failed to execute.',
      'custom_unknown_rule' => ':attribute has an unknown client rule :rule.',
      'invalid_value' => 'Invalid value.',
      'not_saved_check_fields' => 'Not saved. Check the fields marked in red.',
      'not_saved_unexpected' => 'Not saved due to an unexpected error.',
      'rules' => 
      array (
        'array' => ':attribute must be an array.',
        'boolean' => ':attribute must be true or false.',
        'confirmed' => ':attribute confirmation does not match.',
        'email' => ':attribute must be a valid email address.',
        'in' => ':attribute must be one of: :values.',
        'integer' => ':attribute must be an integer.',
        'max' => 
        array (
          'array' => ':attribute may not contain more than :max items.',
          'numeric' => ':attribute may not be greater than :max.',
          'string' => ':attribute may not be greater than :max characters.',
        ),
        'min' => 
        array (
          'array' => ':attribute must contain at least :min items.',
          'numeric' => ':attribute must be at least :min.',
          'string' => ':attribute must be at least :min characters.',
        ),
        'not_regex' => ':attribute format is invalid.',
        'numeric' => ':attribute must be a number.',
        'regex' => ':attribute format is invalid.',
        'required' => ':attribute is required.',
        'same' => ':attribute must match :other.',
        'string' => ':attribute must be a string.',
      ),
      'this_field' => 'This field',
    ),
  ),
);
