<?php

add_filter('zw_webapp_send_notification', 'zw_webapp_send_notification', 10, 2);
add_filter('zw_webapp_title', 'zw_webapp_push_title', 10, 2);

// Render push notification toggle in the Publish metabox (editors and above only)
add_action('post_submitbox_misc_actions', function () {
    $post = get_post();
    if (!$post || $post->post_type !== 'post' || !current_user_can('edit_others_posts')) {
        return;
    }
    $enabled = (bool) get_post_meta($post->ID, 'push_post', true);
    wp_nonce_field('zw_push_post_nonce', 'zw_push_post_nonce');
    ?>
    <div class="misc-pub-section misc-pub-push">
        <span class="dashicons dashicons-bell" style="color: #82878c;"></span>
        <?php echo esc_html__('Pushbericht:', 'streekomroep'); ?>
        <input type="hidden" id="push-post-value" name="push_post" value="<?php echo $enabled ? '1' : '0'; ?>" />
        <a href="#" id="push-toggle" role="button"><b><?php echo $enabled ? esc_html__('Ja', 'streekomroep') : esc_html__('Nee', 'streekomroep'); ?></b></a>
    </div>
    <script>
    jQuery(function($) {
        $('#push-toggle').on('click', function(e) {
            e.preventDefault();
            var $input = $('#push-post-value');
            var on = $input.val() === '1';
            $input.val(on ? '0' : '1');
            $(this).find('b').text(on ? 'Nee' : 'Ja');
        });
    });
    </script>
    <?php
});

// Save push_post meta from the Publish metabox (editors and above only)
add_action('save_post_post', function ($post_id) {
    if (!isset($_POST['zw_push_post_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['zw_push_post_nonce'])), 'zw_push_post_nonce')) {
        return;
    }
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }
    if (!current_user_can('edit_others_posts')) {
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

    // Same primary-term-with-fallback rule as on the site (classmap maps 'post' to Streekomroep\Post).
    // The filter is fired by the external webapp plugin, so guard the type: only our Post
    // subclass exposes region(); a plain page/CPT would otherwise fatal on the method call.
    $post = Timber::get_post($post_id);
    $region = $post instanceof \Streekomroep\Post ? $post->region() : null;

    return $region ? $region->name : $title;
}
