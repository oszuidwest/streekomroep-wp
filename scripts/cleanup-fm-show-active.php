<?php
/**
 * Replace the obsolete fm_show_actief flag with the WordPress post status.
 *
 * Published FM shows that were inactive become drafts. Once every status update
 * succeeds, the obsolete ACF value and field-reference metadata are removed.
 *
 * Usage:
 *   wp eval-file scripts/cleanup-fm-show-active.php dry-run
 *   wp eval-file scripts/cleanup-fm-show-active.php
 */

if (!defined('ABSPATH') || !defined('WP_CLI') || !WP_CLI) {
    echo 'Run this with: wp eval-file scripts/cleanup-fm-show-active.php' . PHP_EOL;
    exit(1);
}

$dry_run = isset($args) && in_array('dry-run', $args, true);

if ($dry_run) {
    WP_CLI::log('DRY RUN — no changes will be made.');
}

global $wpdb;

// Delete the ACF field references before the values. If cleanup stops halfway,
// the old values still contain everything needed for a safe rerun.
$meta_keys = ['_fm_show_actief', 'fm_show_actief'];
$meta_rows_by_key = [];

foreach ($meta_keys as $meta_key) {
    $meta_rows_by_key[$meta_key] = (int) $wpdb->get_var($wpdb->prepare(
        'SELECT COUNT(*) FROM ' . $wpdb->postmeta . ' WHERE meta_key = %s',
        $meta_key
    ));
}

$meta_rows = array_sum($meta_rows_by_key);

// Makes a completed cleanup safe to run again. Without the old field, a missing
// value must not be reinterpreted as an inactive show on a later invocation.
if ($meta_rows === 0) {
    WP_CLI::success('Nothing to clean up; no fm_show_actief metadata remains.');
    return;
}

$show_ids = get_posts([
    'post_type' => 'fm',
    'post_status' => ['publish', 'draft', 'pending', 'future', 'private', 'trash'],
    'posts_per_page' => -1,
    'orderby' => 'ID',
    'order' => 'ASC',
    'fields' => 'ids',
    'suppress_filters' => true,
]);

$inactive_published_ids = array_values(array_filter(
    $show_ids,
    static fn ($show_id) => get_post_status($show_id) === 'publish'
        && !(bool) get_post_meta($show_id, 'fm_show_actief', true)
));

WP_CLI::log(sprintf(
    'Found %d FM shows; %d published inactive shows need to become drafts.',
    count($show_ids),
    count($inactive_published_ids)
));

$updated = 0;
$migration_errors = 0;

foreach ($inactive_published_ids as $show_id) {
    $show_title = get_the_title($show_id);

    if ($dry_run) {
        WP_CLI::log(sprintf('Show %d (%s): would change publish to draft.', $show_id, $show_title));
        $updated++;
        continue;
    }

    $result = wp_update_post([
        'ID' => $show_id,
        'post_status' => 'draft',
    ], true);

    if (is_wp_error($result)) {
        WP_CLI::warning(sprintf(
            'Show %d (%s): failed — %s',
            $show_id,
            $show_title,
            $result->get_error_message()
        ));
        $migration_errors++;
        continue;
    }

    WP_CLI::log(sprintf('Show %d (%s): changed publish to draft.', $show_id, $show_title));
    $updated++;
}

if ($dry_run) {
    WP_CLI::log(sprintf('Would delete %d obsolete postmeta rows.', $meta_rows));
    WP_CLI::success(sprintf(
        'Dry run complete. Would draft: %d, Would delete metadata: %d, Errors: %d.',
        $updated,
        $meta_rows,
        $migration_errors
    ));
    return;
}

if ($migration_errors > 0) {
    WP_CLI::error(sprintf(
        'Stopped after %d status updates with %d errors; obsolete metadata was kept so the script can be rerun safely.',
        $updated,
        $migration_errors
    ));
}

foreach ($meta_keys as $meta_key) {
    if ($meta_rows_by_key[$meta_key] > 0 && !delete_metadata('post', 0, $meta_key, '', true)) {
        WP_CLI::error(sprintf(
            'Failed to delete %s metadata; status changes are complete and the script can be rerun safely.',
            $meta_key
        ));
    }
}

WP_CLI::success(sprintf(
    'Done. Drafted: %d, Deleted metadata rows: %d, Errors: 0.',
    $updated,
    $meta_rows
));
