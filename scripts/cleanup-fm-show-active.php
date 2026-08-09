<?php
/**
 * Removes the obsolete fm_show_actief ACF metadata.
 *
 * This does not change post statuses. WordPress post status is the only source
 * of truth for whether an FM show is publicly visible.
 *
 * Usage:
 *
 *   wp eval-file scripts/cleanup-fm-show-active.php dry-run
 *   wp eval-file scripts/cleanup-fm-show-active.php
 *
 * @package Streekomroep
 */

if (!defined('ABSPATH') || !defined('WP_CLI') || !WP_CLI) {
    echo 'Run this with: wp eval-file scripts/cleanup-fm-show-active.php' . PHP_EOL;
    exit(1);
}

$dry_run = isset($args) && in_array('dry-run', $args, true);
$meta_keys = ['_fm_show_actief', 'fm_show_actief'];
$meta_rows_by_key = [];

global $wpdb;

foreach ($meta_keys as $meta_key) {
    $meta_rows_by_key[$meta_key] = (int) $wpdb->get_var($wpdb->prepare(
        'SELECT COUNT(*) FROM ' . $wpdb->postmeta . ' WHERE meta_key = %s',
        $meta_key
    ));
}

$meta_rows = array_sum($meta_rows_by_key);

if ($dry_run) {
    WP_CLI::success(sprintf('Would delete %d obsolete postmeta rows.', $meta_rows));
    return;
}

foreach ($meta_keys as $meta_key) {
    if ($meta_rows_by_key[$meta_key] > 0 && !delete_metadata('post', 0, $meta_key, '', true)) {
        WP_CLI::error(sprintf('Failed to delete %s metadata.', $meta_key));
    }
}

WP_CLI::success(sprintf('Deleted %d obsolete postmeta rows.', $meta_rows));
