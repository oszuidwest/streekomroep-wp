<?php

add_filter('zw_webapp_send_notification', 'zw_webapp_send_notification', 10, 2);
add_filter('zw_webapp_title', 'zw_webapp_push_title', 10, 2);

// Render push notification toggle in the Publish metabox
add_action('post_submitbox_misc_actions', function () {
    $post = get_post();
    if (!$post || $post->post_type !== 'post') {
        return;
    }
    $enabled = (bool) get_post_meta($post->ID, 'push_post', true);
    wp_nonce_field('zw_push_post_nonce', 'zw_push_post_nonce');
    ?>
    <div class="misc-pub-section misc-pub-push">
        <span class="dashicons dashicons-bell" style="color: #82878c;"></span>
        <span id="push-display">
            <?php echo esc_html__('Pushbericht:', 'streekomroep'); ?>
            <b id="push-display-value"><?php echo $enabled ? esc_html__('Ja', 'streekomroep') : esc_html__('Nee', 'streekomroep'); ?></b>
        </span>
        <a href="#push-select" class="edit-push hide-if-no-js" role="button">
            <span aria-hidden="true"><?php echo esc_html__('Bewerk', 'streekomroep'); ?></span>
        </a>
        <div id="push-select" class="hide-if-js">
            <input type="hidden" name="push_post" value="0" />
            <select name="push_post" id="push-post-select">
                <option value="0" <?php selected(!$enabled); ?>><?php echo esc_html__('Nee', 'streekomroep'); ?></option>
                <option value="1" <?php selected($enabled); ?>><?php echo esc_html__('Ja', 'streekomroep'); ?></option>
            </select>
            <a href="#push-display" class="save-push hide-if-no-js button"><?php echo esc_html__('OK', 'streekomroep'); ?></a>
            <a href="#push-display" class="cancel-push hide-if-no-js button-cancel"><?php echo esc_html__('Annuleren', 'streekomroep'); ?></a>
        </div>
    </div>
    <script>
    jQuery(function($) {
        var $section = $('.misc-pub-push');
        var $select = $('#push-select');
        var $dropdown = $('#push-post-select');
        var $display = $('#push-display-value');
        var initial;

        function storeInitial() { initial = $dropdown.val(); }
        storeInitial();

        $section.on('click', '.edit-push', function(e) {
            e.preventDefault();
            storeInitial();
            $select.slideDown(100);
            $(this).hide();
        });

        $section.on('click', '.save-push', function(e) {
            e.preventDefault();
            $display.text($dropdown.val() === '1' ? 'Ja' : 'Nee');
            $select.slideUp(100);
            $section.find('.edit-push').show();
        });

        $section.on('click', '.cancel-push', function(e) {
            e.preventDefault();
            $dropdown.val(initial);
            $select.slideUp(100);
            $section.find('.edit-push').show();
        });
    });
    </script>
    <?php
});

// Save push_post meta from the Publish metabox
add_action('save_post_post', function ($post_id) {
    if (!isset($_POST['zw_push_post_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['zw_push_post_nonce'])), 'zw_push_post_nonce')) {
        return;
    }
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }
    update_post_meta($post_id, 'push_post', !empty($_POST['push_post']) ? '1' : '0');
});

function zw_webapp_send_notification($do_send, $post_id)
{
    return (bool) get_post_meta($post_id, 'push_post', true);
}

function zw_webapp_push_title($title, $post_id)
{
    if (has_term('breaking', 'ranking', $post_id)) {
        return 'Breaking';
    }
    if (has_term('leestip', 'ranking', $post_id)) {
        return 'Leestip';
    }

    $yoast_primary_term = get_post_meta($post_id, '_yoast_wpseo_primary_regio', true) ?: '';
    if ($yoast_primary_term) {
        $term = get_term($yoast_primary_term, 'regio');
        $yoast_primary_term = $term ? $term->name : '';
    } else {
        $terms = get_the_terms($post_id, 'regio');
        $yoast_primary_term = $terms && !is_wp_error($terms) ? $terms[0]->name : '';
    }

    if (!empty($yoast_primary_term)) {
        return $yoast_primary_term;
    }

    return $title;
}
