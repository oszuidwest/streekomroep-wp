<?php
// Register Ranking Taxonomy
$labels = [
    'name' => 'Rankings',
    'singular_name' => 'Ranking',
    'menu_name' => 'Ranking',
    'all_items' => 'Alle rankings',
    'new_item_name' => 'Nieuwe ranking',
    'add_new_item' => 'Voeg nieuwe ranking toe',
    'edit_item' => 'Bewerk ranking',
    'update_item' => 'Update ranking',
    'view_item' => 'Bekijk rankings',
    'no_terms' => 'Geen rankings',
];
$args = [
    'labels' => $labels,
    'hierarchical' => false,
    'public' => false,
    'show_ui' => true,
    'show_in_nav_menus' => true,
    'rewrite'=>false,
    'meta_box_cb' => 'post_categories_meta_box', // re-use category checkbox list
];
register_taxonomy('rank', ['post'], $args);
