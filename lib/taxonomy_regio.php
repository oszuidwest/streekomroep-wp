<?php
// Register Regio Taxonomy
	$labels = array(
		'name'                       => 'Regio\'s',
		'singular_name'              => 'Regio',
		'menu_name'                  => 'Regio\'s',
		'all_items'                  => 'Alle regio\'s',
		'new_item_name'              => 'Nieuwe regio',
		'add_new_item'               => 'Voeg nieuwe regio toe',
		'edit_item'                  => 'Bewerk regio',
		'update_item'                => 'Update regio',
		'view_item'                  => 'Bekijk regio',
		'no_terms'                   => 'Geen regio\'s',
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
		'rest_base'                  => 'regio',
	);
	register_taxonomy( 'regio', array( 'post', 'fragment', 'agenda' ), $args );
