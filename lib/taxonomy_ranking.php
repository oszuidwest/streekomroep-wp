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
    'default_term'               => [
        'name' => 'Nieuws (standaard)',
        'slug' => 'nieuws',
    ],
    'capabilities'               => [
        'manage_terms' => 'do_not_allow',
        'edit_terms'   => 'do_not_allow',
        'delete_terms' => 'do_not_allow',
        'assign_terms' => 'edit_posts',
    ],
];
register_taxonomy('ranking', ['post'], $args);

// Move ranking metabox to the top of the side column, right after Publish
add_action('add_meta_boxes_post', function () {
    remove_meta_box('rankingdiv', 'post', 'side');
    add_meta_box(
        'rankingdiv',
        'Rankings',
        'post_categories_meta_box',
        'post',
        'side',
        'high',
        ['taxonomy' => 'ranking']
    );
}, 20);

// Disable Yoast SEO primary term picker for this taxonomy
add_filter('wpseo_primary_term_taxonomies', function ($taxonomies) {
    unset($taxonomies['ranking']);
    return $taxonomies;
});

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
