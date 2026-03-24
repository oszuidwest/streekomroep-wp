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
    'meta_box_sanitize_cb'       => 'zw_ranking_sanitize_cb',
];
register_taxonomy('ranking', ['post'], $args);

/**
 * Sanitize ranking checkbox input for the admin edit screen.
 *
 * Because meta_box_cb is false (custom UI), WordPress defaults to the
 * tag/input sanitizer instead of the checkbox sanitizer. This callback
 * corrects that and enforces the business rule: when no ranking is
 * selected, substitute the 'nieuws' default term. This runs inside
 * edit_post() before wp_insert_post(), preventing core's default_term
 * logic from restoring the previous terms on an empty submission.
 */
function zw_ranking_sanitize_cb($taxonomy, $terms)
{
    $terms = taxonomy_meta_box_sanitize_cb_checkboxes($taxonomy, $terms);
    $terms = array_filter($terms);

    if (empty($terms)) {
        $default_term_id = (int) get_option('default_term_' . $taxonomy);
        if ($default_term_id) {
            return [$default_term_id];
        }
    }

    return $terms;
}

// Render ranking inside the Publish metabox, matching Status/Visibility style
add_action('post_submitbox_misc_actions', function () {
    $post = get_post();
    if (!$post || $post->post_type !== 'post') {
        return;
    }

    $terms = get_the_terms($post->ID, 'ranking');
    $display = 'Nieuws (standaard)';
    if ($terms && !is_wp_error($terms)) {
        $display = implode(', ', wp_list_pluck($terms, 'name'));
    }
    ?>
    <div class="misc-pub-section misc-pub-ranking">
        <span class="dashicons dashicons-sort" style="color: #82878c;"></span>
        <span id="ranking-display">
            <?php echo esc_html__('Ranking:', 'streekomroep'); ?>
            <b id="ranking-display-value"><?php echo esc_html($display); ?></b>
        </span>
        <a href="#ranking-select" class="edit-ranking hide-if-no-js" role="button">
            <span aria-hidden="true"><?php echo esc_html__('Bewerk', 'streekomroep'); ?></span>
        </a>
        <div id="ranking-select" class="hide-if-js">
            <?php // Falsey sentinel ensures tax_input[ranking] is always present; zw_ranking_sanitize_cb maps an empty selection to the default term. ?>
            <input type="hidden" name="tax_input[ranking][]" value="0" />
            <ul class="categorychecklist form-no-clear" style="margin: 4px 0 0; list-style: none;">
    <?php wp_terms_checklist($post->ID, ['taxonomy' => 'ranking', 'checked_ontop' => false]); ?>
            </ul>
            <a href="#ranking-display" class="save-ranking hide-if-no-js button"><?php echo esc_html__('OK', 'streekomroep'); ?></a>
            <a href="#ranking-display" class="cancel-ranking hide-if-no-js button-cancel"><?php echo esc_html__('Annuleren', 'streekomroep'); ?></a>
        </div>
    </div>
    <script>
    jQuery(function($) {
        var $section = $('.misc-pub-ranking');
        var $select = $('#ranking-select');
        var $display = $('#ranking-display-value');
        var initial = [];

        function storeInitial() {
            initial = [];
            $select.find('input:checked').each(function() { initial.push(this.value); });
        }

        function updateDisplay() {
            var names = [];
            $select.find('input:checked').each(function() {
                names.push($(this).parent().text().trim());
            });
            $display.text(names.length ? names.join(', ') : 'Nieuws (standaard)');
        }

        storeInitial();

        $section.on('click', '.edit-ranking', function(e) {
            e.preventDefault();
            storeInitial();
            $select.slideDown(100);
            $(this).hide();
        });

        $section.on('click', '.save-ranking', function(e) {
            e.preventDefault();
            updateDisplay();
            $select.slideUp(100);
            $section.find('.edit-ranking').show();
        });

        $section.on('click', '.cancel-ranking', function(e) {
            e.preventDefault();
            $select.find('input[type="checkbox"]').each(function() {
                this.checked = initial.indexOf(this.value) !== -1;
            });
            $select.slideUp(100);
            $section.find('.edit-ranking').show();
        });
    });
    </script>
    <?php
});

/**
 * Re-assign the default ranking term if a post ends up with none.
 *
 * Guards:
 * - Skips posts being deleted (flagged via before_delete_post) to avoid
 *   creating orphaned term_relationships rows for posts about to be removed.
 * - Checks term_exists() before re-assigning to prevent infinite recursion
 *   when default_term_ranking points to a stale/deleted term ID.
 */
function zw_enforce_ranking_default($object_id, $taxonomy)
{
    global $zw_ranking_deleting;

    if ($taxonomy !== 'ranking') {
        return;
    }

    if (get_post_type($object_id) !== 'post') {
        return;
    }

    if (!empty($zw_ranking_deleting[$object_id])) {
        return;
    }

    $existing = get_the_terms($object_id, 'ranking');
    if (!empty($existing) && !is_wp_error($existing)) {
        return;
    }

    $default_term_id = (int) get_option('default_term_ranking');
    if ($default_term_id && term_exists($default_term_id, 'ranking')) {
        wp_set_object_terms($object_id, [$default_term_id], 'ranking');
    }
}

// Flag posts being deleted so zw_enforce_ranking_default() skips them.
add_action('before_delete_post', function ($post_id) {
    global $zw_ranking_deleting;
    $zw_ranking_deleting[$post_id] = true;
});
add_action('after_delete_post', function ($post_id) {
    global $zw_ranking_deleting;
    unset($zw_ranking_deleting[$post_id]);
});

// Covers wp_set_object_terms() paths: REST API, WP-CLI, admin, plugins.
add_action('set_object_terms', function ($object_id, $terms, $tt_ids, $taxonomy) {
    if (!empty($tt_ids)) {
        return;
    }
    zw_enforce_ranking_default($object_id, $taxonomy);
}, 10, 4);

// Covers wp_remove_object_terms() and wp_delete_object_term_relationships() paths.
add_action('deleted_term_relationships', function ($object_id, $tt_ids, $taxonomy) {
    zw_enforce_ranking_default($object_id, $taxonomy);
}, 10, 3);

// Disable Yoast SEO primary term picker for this taxonomy
add_filter('wpseo_primary_term_taxonomies', function ($taxonomies) {
    unset($taxonomies['ranking']);
    return $taxonomies;
});

// Seed ranking terms if they don't exist (skipped when already seeded)
add_action('init', function () {
    if (get_transient('zw_ranking_terms_seeded')) {
        return;
    }

    $terms = [
        'breaking'   => 'Breaking',
        'top-story'  => 'Top story',
        'leestip'    => 'Leestip',
        'nieuws'     => 'Nieuws (standaard)',
        'achterkant' => 'Achterkant',
    ];

    foreach ($terms as $slug => $name) {
        if (!get_term_by('slug', $slug, 'ranking')) {
            wp_insert_term($name, 'ranking', ['slug' => $slug]);
        }
    }

    // Only cache when all required slugs actually exist
    $all_exist = true;
    foreach (array_keys($terms) as $slug) {
        if (!get_term_by('slug', $slug, 'ranking')) {
            $all_exist = false;
            break;
        }
    }

    if ($all_exist) {
        set_transient('zw_ranking_terms_seeded', 1, DAY_IN_SECONDS);
    }
}, 20);
