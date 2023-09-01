<?php
// Register TV Shows Post Type
    $labels = array(
        'name'                  => 'TV programma\'s',
        'singular_name'         => 'TV programma',
        'menu_name'             => 'TV programma\'s',
        'name_admin_bar'        => 'TV programma',
        'all_items'             => 'Alle programma\'s',
        'add_new_item'          => 'Nieuw programma',
        'add_new'               => 'Voeg programma toe',
        'new_item'              => 'Nieuw programma',
        'edit_item'             => 'Bewerk programma',
        'update_item'           => 'Update programma',
        'view_item'             => 'Bekijk programma',
        'view_items'            => 'Bekijk programma',
        'search_items'          => 'Zoek programma\'s',
    );
    $args = array(
        'label'                 => 'TV programma\'s',
        'description'           => 'programma\'s die op TV worden uitgezonden',
        'labels'                => $labels,
        'supports'              => [ 'title', 'editor', 'thumbnail' ],
        'hierarchical'          => false,
        'public'                => true,
        'show_ui'               => true,
        'show_in_menu'          => true,
        'menu_position'         => 5,
        'menu_icon'             => 'dashicons-video-alt2',
        'show_in_admin_bar'     => true,
        'show_in_nav_menus'     => true,
        'can_export'            => true,
        'has_archive'           => true,
        'exclude_from_search'   => true,
        'publicly_queryable'    => true,
        'capability_type'       => 'post',
        'show_in_rest'          => true,
        'rest_base'             => 'tv',
        'rewrite'               => [ 'slug' => 'tv', 'with_front' => true ],
    );
    register_post_type('tv', $args);
