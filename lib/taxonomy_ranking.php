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
        <span class="dashicons dashicons-star-filled" style="color: #82878c;"></span>
        <span id="ranking-display">
            <?php echo esc_html__('Ranking:', 'streekomroep'); ?>
            <b id="ranking-display-value"><?php echo esc_html($display); ?></b>
        </span>
        <a href="#ranking-select" class="edit-ranking hide-if-no-js" role="button">
            <span aria-hidden="true"><?php echo esc_html__('Bewerk', 'streekomroep'); ?></span>
        </a>
        <div id="ranking-select" class="hide-if-js">
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
