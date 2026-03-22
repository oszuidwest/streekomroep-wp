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
    'meta_box_cb'                => false,
];
register_taxonomy('ranking', ['post'], $args);

// Render ranking checkboxes inside the Publish metabox
add_action('post_submitbox_misc_actions', function () {
    $post = get_post();
    if (!$post || $post->post_type !== 'post') {
        return;
    }
    ?>
    <div class="misc-pub-section">
        <strong>Ranking:</strong>
        <input type="hidden" name="tax_input[ranking][]" value="0" />
        <ul class="categorychecklist form-no-clear" style="margin: 4px 0 0; padding: 0; list-style: none;">
    <?php wp_terms_checklist($post->ID, ['taxonomy' => 'ranking', 'checked_ontop' => false]); ?>
        </ul>
    </div>
    <?php
});

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
