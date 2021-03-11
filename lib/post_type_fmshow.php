<?php
// Register FM Shows Post Type
	$labels = array(
		'name'                  => 'FM programma\'s',
		'singular_name'         => 'FM programma',
		'menu_name'             => 'FM programma\'s',
		'name_admin_bar'        => 'FM programma',
		'all_items'             => 'Alle programma\'s',
		'add_new_item'          => 'Nieuw programma',
		'add_new'               => 'Voeg programma toe',
		'new_item'              => 'Nieuwe programma',
		'edit_item'             => 'Bewerk programma',
		'update_item'           => 'Update programma',
		'view_item'             => 'Bekijk programma',
		'view_items'            => 'Bekijk programma\'s',
		'search_items'          => 'Zoek programma\'s',
	);
	$args = array(
		'label'                 => 'FM programma\'s',
		'description'           => 'Programma\'s die op FM worden uitgezonden',
		'labels'                => $labels,
		'supports'              => [ 'title', 'editor', 'thumbnail' ],
		'hierarchical'          => false,
		'public'                => true,
		'show_ui'               => true,
		'show_in_menu'          => true,
		'menu_position'         => 5,
		'menu_icon'             => 'dashicons-microphone',
		'show_in_admin_bar'     => true,
		'show_in_nav_menus'     => true,
		'can_export'            => true,
		'has_archive'           => true,
		'exclude_from_search'   => true,
		'publicly_queryable'    => true,
		'capability_type'       => 'post',
		'show_in_rest'          => true,
		'rest_base'             => 'tv',
		'rewrite'               => [ 'slug' => 'fm', 'with_front' => true ],
	);
	register_post_type( 'fm', $args );
