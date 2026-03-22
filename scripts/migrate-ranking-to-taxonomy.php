<?php
/**
 * Migrate post_ranking from ACF postmeta to ranking taxonomy.
 *
 * Usage:
 *   wp eval-file scripts/migrate-ranking-to-taxonomy.php dry-run
 *   wp eval-file scripts/migrate-ranking-to-taxonomy.php
 */

if (!defined('ABSPATH')) {
    echo "Run this with: wp eval-file scripts/migrate-ranking-to-taxonomy.php\n";
    exit(1);
}

$dry_run = isset($args) && in_array('dry-run', $args, true);

if ($dry_run) {
    WP_CLI::log('DRY RUN — no changes will be made.');
}

$value_to_slug = [
    '1' => 'breaking',
    '2' => 'top-story',
    '3' => 'leestip',
    '5' => 'nieuws',
    '6' => 'achterkant',
];

// Verify all target terms exist
foreach ($value_to_slug as $value => $slug) {
    if (!term_exists($slug, 'ranking')) {
        WP_CLI::error("Term '{$slug}' does not exist in taxonomy 'ranking'. Register the taxonomy first.");
    }
}

// Get all posts that have post_ranking meta
global $wpdb;
$results = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s",
        'post_ranking'
    )
);

$migrated = 0;
$errors = 0;

foreach ($results as $row) {
    $post_id = (int) $row->post_id;
    $values = maybe_unserialize($row->meta_value);

    if (!is_array($values)) {
        $values = [$values];
    }

    $slugs = [];
    foreach ($values as $v) {
        $v = (string) $v;
        if (isset($value_to_slug[$v])) {
            $slugs[] = $value_to_slug[$v];
        } else {
            WP_CLI::warning("Post {$post_id}: unknown ranking value '{$v}', skipping this value.");
            $errors++;
        }
    }

    if (empty($slugs)) {
        $slugs = ['nieuws'];
    }

    if ($dry_run) {
        WP_CLI::log("Post {$post_id}: would assign terms: " . implode(', ', $slugs));
    } else {
        $result = wp_set_object_terms($post_id, $slugs, 'ranking');
        if (is_wp_error($result)) {
            WP_CLI::warning("Post {$post_id}: failed — " . $result->get_error_message());
            $errors++;
            continue;
        }
    }
    $migrated++;
}

// Handle posts without any ranking meta
$orphan_ids = $wpdb->get_col(
    "SELECT ID FROM {$wpdb->posts}
     WHERE post_type = 'post'
       AND post_status IN ('publish', 'draft', 'pending', 'future', 'private')
       AND ID NOT IN (
           SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'post_ranking'
       )"
);

$defaulted = 0;
foreach ($orphan_ids as $post_id) {
    $post_id = (int) $post_id;
    if ($dry_run) {
        WP_CLI::log("Post {$post_id}: no existing ranking, would assign 'nieuws'");
    } else {
        wp_set_object_terms($post_id, 'nieuws', 'ranking');
    }
    $defaulted++;
}

WP_CLI::success("Done. Migrated: {$migrated}, Defaulted: {$defaulted}, Errors: {$errors}");

if (!$dry_run && $errors === 0) {
    WP_CLI::log('');
    WP_CLI::log('To clean up old postmeta after verifying the migration:');
    WP_CLI::log("  wp eval 'global \$wpdb; \$deleted = \$wpdb->delete(\$wpdb->postmeta, [\"meta_key\" => \"post_ranking\"]); \$deleted2 = \$wpdb->delete(\$wpdb->postmeta, [\"meta_key\" => \"_post_ranking\"]); echo \"Deleted: \$deleted + \$deleted2 rows\\n\";'");
}
