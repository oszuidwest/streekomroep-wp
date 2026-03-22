<?php

add_filter('zw_webapp_send_notification', 'zw_webapp_send_notification', 10, 2);
add_filter('zw_webapp_title', 'zw_webapp_push_title', 10, 2);

// Render push notification checkbox in the Publish metabox
add_action('post_submitbox_misc_actions', function () {
    $post = get_post();
    if (!$post || $post->post_type !== 'post') {
        return;
    }
    $checked = (bool) get_post_meta($post->ID, 'push_post', true);
    wp_nonce_field('zw_push_post_nonce', 'zw_push_post_nonce');
    ?>
    <div class="misc-pub-section misc-pub-push">
        <span class="dashicons dashicons-bell" style="color: #82878c;"></span>
        <label>
            <input type="hidden" name="push_post" value="0" />
            <input type="checkbox" name="push_post" value="1" <?php checked($checked); ?> />
            <?php echo esc_html__('Pushbericht versturen', 'streekomroep'); ?>
        </label>
    </div>
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
