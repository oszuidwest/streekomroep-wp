<?php
// Register Ranking Taxonomy
$labels = [
    'name'                       => 'Rankings',
    'singular_name'              => 'Ranking',
    'menu_name'                  => 'Rankings',
    'all_items'                  => 'Alle rankings',
    'edit_item'                  => 'Bewerk ranking',
    'update_item'                => 'Update ranking',
    'view_item'                  => 'Bekijk ranking',
    'no_terms'                   => 'Geen rankings',
];
$args = [
    'labels'                     => $labels,
    'hierarchical'               => true,
    'public'                     => false,
    'publicly_queryable'         => false,
    'show_ui'                    => true,
    'show_admin_column'          => true,
    'show_in_nav_menus'          => false,
    'show_tagcloud'              => false,
    'show_in_rest'               => true,
    'rest_base'                  => 'ranking',
    'meta_box_cb'                => false,
];
register_taxonomy('ranking', ['post'], $args);

// Seed ranking terms if they don't exist
add_action('init', function () {
    $terms = [
        'breaking'   => 'Breaking',
        'top-story'  => 'Top story',
        'leestip'    => 'Leestip',
        'nieuws'     => 'Nieuws (standaard)',
        'achterkant' => 'Achterkant',
    ];
    foreach ($terms as $slug => $name) {
        if (!term_exists($slug, 'ranking')) {
            wp_insert_term($name, 'ranking', ['slug' => $slug]);
        }
    }
}, 20);

// Assign default ranking 'nieuws' to new posts without a ranking
add_action('save_post_post', function ($post_id) {
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }
    $terms = get_the_terms($post_id, 'ranking');
    if (empty($terms) || is_wp_error($terms)) {
        wp_set_object_terms($post_id, 'nieuws', 'ranking');
    }
}, 20);
