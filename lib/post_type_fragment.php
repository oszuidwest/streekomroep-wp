<?php
// Register Fragment Post Type
$labels = array(
    'name'                  => 'Fragmenten',
    'singular_name'         => 'Fragment',
    'menu_name'             => 'Fragmenten',
    'name_admin_bar'        => 'Fragment',
    'all_items'             => 'Alle fragmenten',
    'add_new_item'          => 'Nieuw fragment',
    'add_new'               => 'Voeg fragment toe',
    'new_item'              => 'Nieuw fragment',
    'edit_item'             => 'Bewerk fragment',
    'update_item'           => 'Update fragment',
    'view_item'             => 'Bekijk fragment',
    'view_items'            => 'Bekijk fragmenten',
    'search_items'          => 'Zoek fragment',
);
$args = array(
    'label'                 => 'Fragment',
    'description'           => 'Nieuwsfragmenten',
    'labels'                => $labels,
    'supports'              => array( 'title', 'author', 'editor', 'thumbnail', 'comments', 'revisions', 'custom-fields', 'excerpt' ),
    'taxonomies'            => array( 'category', 'post_tag', 'regio', 'dossier' ),
    'hierarchical'          => false,
    'public'                => true,
    'show_ui'               => true,
    'show_in_menu'          => true,
    'menu_position'         => 5,
    'menu_icon'             => 'dashicons-embed-video',
    'show_in_admin_bar'     => true,
    'show_in_nav_menus'     => true,
    'can_export'            => true,
    'has_archive'           => true,
    'exclude_from_search'   => false,
    'publicly_queryable'    => true,
    'capability_type'       => 'post',
    'show_in_rest'          => true,
    'rest_base'             => 'fragmenten',
);
register_post_type('fragment', $args);
