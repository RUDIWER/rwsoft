<?php

return array (
  'backend' => 
  array (
    'messages' => 
    array (
      'chart_deleted' => 'Graphique supprime avec succes.',
      'chart_saved' => 'Graphique enregistre avec succes.',
      'export_deleted' => 'Configuration d\'export supprimee avec succes.',
      'export_saved' => 'Configuration d\'export enregistree avec succes.',
      'extra_field_not_editable' => 'Le champ \':field\' n\'est pas modifiable.',
      'field_not_editable' => 'Le champ n\'est pas modifiable.',
      'field_required' => 'Le champ est obligatoire.',
      'model_rules_missing' => 'Les regles de validation du modele sont manquantes.',
      'validation_rules_missing_for_field' => 'Les regles de validation pour le champ \':field\' sont manquantes.',
      'validation_rules_required' => 'Les regles de validation sont obligatoires.',
      'validation_rules_required_for_extra_field' => 'Les regles de validation pour le champ supplementaire \':field\' sont obligatoires.',
      'validation_type_required' => 'Le type de validation est obligatoire.',
    ),
  ),
  'vue' => 
  array (
    'actions' => 
    array (
      'back' => 'Retour',
      'cancel_new_row' => 'Annuler la nouvelle ligne',
      'clear' => 'Effacer',
      'close' => 'Fermer',
      'delete' => 'Supprimer',
      'edit' => 'Modifier',
      'insert_above' => 'Inserer au-dessus',
      'new' => 'Nouveau',
      'new_row' => 'Nouvelle ligne',
      'save' => 'Enregistrer',
      'view' => 'Voir',
    ),
    'autocomplete' => 
    array (
      'more' => 'plus',
      'no_results' => 'Aucun resultat',
      'use_custom_value' => 'Utiliser une valeur libre:',
    ),
    'charts' => 
    array (
      'actions' => 
      array (
        'print_pdf' => 'Imprimer PDF',
      ),
      'aggregate_items' => 
      array (
        'avg' => 'Moyenne',
        'count' => 'Nombre',
        'max' => 'Maximum',
        'min' => 'Minimum',
        'sum' => 'Somme',
      ),
      'dialog' => 
      array (
        'title_edit' => 'Modifier le graphique',
        'title_list' => 'Gestion des graphiques',
        'title_new' => 'Nouveau graphique',
        'title_view' => 'Voir le graphique',
      ),
      'fields' => 
      array (
        'aggregate' => 'Calcul (agregation)',
        'allow_type_change' => 'Autoriser le changement de type dans la vue',
        'limit' => 'Top N (1-500)',
        'metric_field' => 'Champ metrique',
        'no_series' => '-- Aucune serie --',
        'orientation' => 'Orientation',
        'series_field_optional' => 'Champ serie (optionnel)',
        'show_legend' => 'Afficher la legende',
        'sort' => 'Tri',
        'stacked' => 'Empile',
        'type' => 'Type de graphique',
        'viewer_type' => 'Type de graphique dans la vue',
        'x_field' => 'Champ X',
      ),
      'manage_title' => 'Gestion des graphiques',
      'messages' => 
      array (
        'delete_failed' => 'La suppression de la configuration du graphique a echoue.',
        'load_failed' => 'Impossible de charger les graphiques enregistres pour ce tableau.',
        'loading_data' => 'Chargement des donnees du graphique...',
        'no_renderable_data' => 'Aucune donnee exploitable pour le graphique avec les parametres actuels.',
        'none_saved' => 'Aucun graphique enregistre trouve.',
        'pdf_failed' => 'L\'impression PDF du graphique a echoue.',
        'pdf_not_available' => 'Impossible de generer le PDF car le graphique n\'est pas encore disponible.',
        'render_failed' => 'Impossible de rendre le graphique avec les parametres actuels.',
        'save_failed' => 'L\'enregistrement de la configuration du graphique a echoue.',
        'saved' => 'Configuration du graphique enregistree.',
        'source_load_failed' => 'Impossible de charger les donnees source du graphique.',
        'webgl_unsupported' => 'WebGL n\'est pas pris en charge par ce navigateur ou ce GPU. Choisissez un type de graphique non-WebGL.',
      ),
      'orientation_items' => 
      array (
        'horizontal' => 'Horizontal',
        'vertical' => 'Vertical',
      ),
      'pdf' => 
      array (
        'default_filename' => 'graphique',
        'default_title' => 'Graphique',
        'image_alt' => 'Export du graphique',
      ),
      'placeholders' => 
      array (
        'description' => 'Par exemple: Inscriptions par annee scolaire',
      ),
      'series' => 
      array (
        'total' => 'Total',
      ),
      'sort_direction_items' => 
      array (
        'asc' => 'Croissant',
        'desc' => 'Decroissant',
      ),
      'type_items' => 
      array (
        'bar' => 'Barres',
        'bar3d' => 'Barres 3D',
        'bar3d_webgl' => 'Barres 3D (WebGL)',
        'doughnut' => 'Anneau',
        'line' => 'Ligne',
        'line3d' => 'Ligne 3D',
        'line3d_webgl' => 'Ligne 3D (WebGL)',
        'pie' => 'Camembert',
      ),
      'validation' => 
      array (
        'minimum_required' => 'Renseignez au minimum la description, le champ X et, si necessaire, un champ metrique.',
      ),
    ),
    'columns' => 
    array (
      'action' => 'Action',
      'active' => 'Actif',
      'article_description' => 'Article / Description',
      'created_at' => 'Cree le',
      'description' => 'Description',
      'id' => 'ID',
      'in_menu' => 'Dans le menu',
      'labels' => 'Etiquettes',
      'module' => 'Module',
      'notes' => 'Notes',
      'order' => 'Ordre',
      'owner' => 'Proprietaire',
      'priority' => 'Priorite',
      'product_id' => 'ID produit',
      'route' => 'Route',
      'status' => 'Statut',
      'title' => 'Titre',
    ),
    'common' => 
    array (
      'choose_field' => '-- Choisir un champ --',
      'dash' => '-',
      'description_title' => 'Description / Titre',
      'no' => 'Non',
      'yes' => 'Oui',
    ),
    'excel' => 
    array (
      'actions' => 
      array (
        'download_direct' => 'Telechargement direct',
      ),
      'dialog' => 
      array (
        'title_button' => 'Export Excel',
        'title_edit' => 'Modifier l\'export',
        'title_list' => 'Gestion des exports Excel',
        'title_new' => 'Nouvel export',
      ),
      'fields' => 
      array (
        'select_sort_columns' => 'Selectionner et trier les colonnes',
      ),
      'messages' => 
      array (
        'delete_failed' => 'La suppression de l\'export a echoue.',
        'download_failed' => 'Une erreur est survenue lors de la generation du fichier Excel.',
        'load_failed' => 'Impossible de charger les exports enregistres pour ce tableau.',
        'no_columns_selected' => 'Selectionnez au moins une colonne pour exporter.',
        'no_data' => 'Aucune donnee a exporter.',
        'none_saved' => 'Aucun export enregistre trouve.',
        'save_failed' => 'L\'enregistrement de la configuration d\'export a echoue.',
        'saved' => 'Configuration d\'export enregistree.',
      ),
      'placeholders' => 
      array (
        'description' => 'Par exemple: Apercu des enregistrements actifs',
      ),
    ),
    'filters' => 
    array (
      'aria' => 
      array (
        'filter_column' => 'Filtrer la colonne :label',
        'from_date_for' => 'Filtrer depuis la date pour :label',
        'operator_for' => 'Operateur de filtre pour :label',
        'to_date_for' => 'Filtrer jusqu\'a la date pour :label',
        'value_for' => 'Valeur du filtre pour :label',
      ),
      'choose_value' => 'Choisir une valeur',
      'clear_all' => 'Effacer les filtres',
      'free_text' => 'Texte libre',
      'from' => 'De',
      'modes' => 
      array (
        'after' => 'Apres',
        'before' => 'Avant',
        'between' => 'Entre',
        'contains' => 'Contient',
        'contains_option' => 'Contient l\'option',
        'contains_option_all' => 'Contient l\'option (toutes selectionnees)',
        'equals' => 'Egal a',
        'equals_option' => 'Egal a l\'option',
        'equals_option_exact' => 'Egal a l\'option (ensemble exact)',
        'greater_than' => 'Superieur a',
        'less_than' => 'Inferieur a',
        'not_contains' => 'Ne contient pas',
        'not_equals' => 'Different de',
      ),
      'option_value' => 'Valeur de la liste',
      'to' => 'A',
      'value' => 'Valeur',
    ),
    'search' => 
    array (
      'all_columns' => 'Rechercher dans toutes les colonnes',
    ),
    'table' => 
    array (
      'actions' => 'Actions',
      'aria' => 
      array (
        'edit_field' => 'Modifier :label',
        'new_value_for' => 'Nouvelle valeur pour :label',
        'select_all_visible_rows' => 'Selectionner toutes les lignes visibles',
        'select_row' => 'Selectionner la ligne :id',
      ),
      'column' => 
      array (
        'aria' => 
        array (
          'drag_column' => 'Deplacer la colonne :label',
          'pin_column' => 'Epinglez la colonne :label',
          'resize_column' => 'Redimensionner la colonne :label',
          'toggle_column' => 'Afficher la colonne :label',
        ),
      ),
      'config' => 
      array (
        'enable_horizontal_scroll' => 'Activer le defilement horizontal',
        'height' => 'Hauteur du tableau',
        'restore_default' => 'Restaurer par defaut',
        'show_record_count' => 'Afficher le nombre d\'enregistrements',
        'show_row_quantity_select' => 'Afficher le selecteur du nombre de lignes',
        'title' => 'Configuration',
        'use_pagination' => 'Utiliser la pagination au lieu du defilement infini',
      ),
      'description' => 'Description',
      'id' => 'Id',
      'loading' => 'Chargement...',
      'manual_ordering_active' => 'Ordre manuel actif',
      'no_records' => 'Aucun enregistrement trouve.',
      'record_count' => 'Nombre de lignes: :count',
      'rows_per_page' => 'Lignes par page',
    ),
    'validation' => 
    array (
      'custom' => 
      array (
        'enterprise_be' => ':attribute doit etre un numero d\'entreprise belge valide (KBO/BCE).',
        'iban_be' => ':attribute doit etre un IBAN belge valide (BE + 14 chiffres).',
        'min_words' => ':attribute doit contenir au moins :min mots.',
        'phone_be' => ':attribute doit etre un numero de telephone belge valide.',
        'postcode_be' => ':attribute doit etre un code postal belge valide (1000-9999).',
        'rrn_be' => ':attribute doit etre un numero de registre national belge valide (11 chiffres).',
      ),
      'custom_failed' => ':attribute est invalide pour la regle client :rule.',
      'custom_runtime_error' => 'La regle client :rule ne peut pas etre executee.',
      'custom_unknown_rule' => ':attribute utilise une regle client inconnue :rule.',
      'invalid_value' => 'Valeur invalide.',
      'not_saved_check_fields' => 'Non enregistre. Verifiez les champs marques en rouge.',
      'not_saved_unexpected' => 'Non enregistre suite a une erreur inattendue.',
      'rules' => 
      array (
        'array' => ':attribute doit etre une liste.',
        'boolean' => ':attribute doit etre vrai ou faux.',
        'confirmed' => 'La confirmation de :attribute ne correspond pas.',
        'email' => ':attribute doit etre une adresse e-mail valide.',
        'in' => ':attribute doit etre une des valeurs suivantes: :values.',
        'integer' => ':attribute doit etre un entier.',
        'max' => 
        array (
          'array' => ':attribute ne peut pas contenir plus de :max elements.',
          'numeric' => ':attribute ne peut pas etre superieur a :max.',
          'string' => ':attribute ne peut pas depasser :max caracteres.',
        ),
        'min' => 
        array (
          'array' => ':attribute doit contenir au moins :min elements.',
          'numeric' => ':attribute doit etre au moins :min.',
          'string' => ':attribute doit contenir au moins :min caracteres.',
        ),
        'not_regex' => 'Le format de :attribute est invalide.',
        'numeric' => ':attribute doit etre un nombre.',
        'regex' => 'Le format de :attribute est invalide.',
        'required' => ':attribute est obligatoire.',
        'same' => ':attribute doit correspondre a :other.',
        'string' => ':attribute doit etre un texte.',
      ),
      'this_field' => 'Ce champ',
    ),
  ),
);
