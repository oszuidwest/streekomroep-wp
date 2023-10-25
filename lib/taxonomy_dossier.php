<?php
// Register Dossier Taxonomy
$labels = array(
    'name'                       => 'Dossiers',
    'singular_name'              => 'Dossier',
    'menu_name'                  => 'Dossiers',
    'all_items'                  => 'Alle dossiers',
    'new_item_name'              => 'Nieuw dossier',
    'add_new_item'               => 'Voeg nieuw dossier toe',
    'edit_item'                  => 'Bewerk dossier',
    'update_item'                => 'Update dossier',
    'view_item'                  => 'Bekijk dossier',
    'no_terms'                   => 'Geen dossiers',
);
$args = array(
    'labels'                     => $labels,
    'hierarchical'               => true,
    'public'                     => true,
    'show_ui'                    => true,
    'show_admin_column'          => true,
    'show_in_nav_menus'          => true,
    'show_tagcloud'              => true,
    'show_in_rest'               => true,
    'rest_base'                  => 'dossier',
);
register_taxonomy('dossier', array( 'post', 'fragment' ), $args);
