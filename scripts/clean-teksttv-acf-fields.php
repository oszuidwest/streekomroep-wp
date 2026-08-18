<?php
/**
 * Clean old theme-owned Tekst TV ACF definitions, values, and legacy zw-ttvgpt options.
 *
 * The command is a dry run by default. Back up the database before deleting data.
 * On multisite, run the command separately for each site with --url=<site-url>.
 *
 * Dry run:
 *   wp eval-file scripts/clean-teksttv-acf-fields.php
 *   wp --url=https://example.com eval-file scripts/clean-teksttv-acf-fields.php
 *
 * Delete:
 *   wp eval-file scripts/clean-teksttv-acf-fields.php delete
 *   wp --url=https://example.com eval-file scripts/clean-teksttv-acf-fields.php delete
 */

if (!defined('ABSPATH') || !defined('WP_CLI')) {
    echo 'Run this with: wp eval-file scripts/clean-teksttv-acf-fields.php' . PHP_EOL;
    exit(1);
}

$delete = isset($args) && in_array('delete', $args, true);
$dry_run = !$delete;

if ($dry_run) {
    WP_CLI::log('DRY RUN - no changes will be made. Add "delete" to remove the data.');
}

$field_group_keys = [
    'group_5f21a05a18b57',
    'group_603c10f5364c8',
    'group_66c5010145df1',
    'group_66eedd5fd4889',
    'group_66fc415747160',
    'group_67a26f0a4c5d6',
];

$field_keys = [
    'field_5f21a06d22c58',
    'field_5f74740c7f912',
    'field_603c11fdf28cd',
    'field_603c120ff28ce',
    'field_603c1232f28cf',
    'field_603c126bf28d0',
    // Removed from the JSON field group in PR #117, but its database post may remain.
    'field_665f7219edaef',
    'field_665f7258edaf1',
    'field_665f73aa4951f',
    'field_665f7426c46ce',
    'field_6693cc8b7ce26',
    'field_669d3e44fbe9c',
    'field_669d47472d98a',
    'field_669d48068a773',
    'field_669d484763ed7',
    'field_669d497a8fbc2',
    'field_669d49968fbc3',
    'field_669d49a58fbc4',
    'field_669d49c2261cd',
    'field_669d4a0e6c534',
    'field_669d4a3123880',
    'field_66ad2a3105371',
    'field_66c50b0158e39',
    'field_66c510307dd5d',
    'field_66c511bbf12b2',
    'field_66eedd609ee7e',
    'field_66eedf5d560b3',
    'field_66fc41575b97d',
    'field_671ab2972f163',
    'field_67a26e9f4c5d2',
    'field_67a26ea04c5d3',
    'field_67a26f1a4c5d7',
];

$post_meta_fields = [
    'post_in_kabelkrant',
    'post_kabelkrant_content',
    'post_kabelkrant_content_gpt',
    'post_kabelkrant_dagen',
    // Removed from the JSON field group in PR #117, but stored postmeta may remain.
    'post_kabelkrant_datum_in',
    'post_kabelkrant_datum_uit',
    'post_kabelkrant_extra_afbeeldingen',
];

$term_meta_fields = [
    'teksttv_categorie_afbeelding',
];

// The old options pages used post IDs teksttv_<channel> and teksttv_instellingen.
$acf_option_like_patterns = [
    'teksttv\_%\_teksttv\_blokken%',
    '\_teksttv\_%\_teksttv\_blokken%',
    'teksttv\_%\_teksttv\_ticker%',
    '\_teksttv\_%\_teksttv\_ticker%',
    'teksttv\_%\_teksttv\_reclame%',
    '\_teksttv\_%\_teksttv\_reclame%',
    'teksttv\_instellingen\_openweather\_api\_key',
    '\_teksttv\_instellingen\_openweather\_api\_key',
];

// zw-ttvgpt originally stored these as standalone options. Later versions
// uninstall only the consolidated zw_ttvgpt_settings option, so these rows may remain.
$legacy_option_names = [
    'ttvgpt_api_key',
    'ttvgpt_model',
    'ttvgpt_word_limit',
];

/**
 * Add ACF reference keys for field names.
 *
 * @param array<string> $fields Field names.
 * @return array<string>
 */
function zw_teksttv_cleaner_with_reference_keys(array $fields): array
{
    $keys = [];

    foreach ($fields as $field) {
        $keys[] = $field;
        $keys[] = '_' . $field;
    }

    return $keys;
}

/**
 * Abort when the last database read failed.
 *
 * @param string $context Description of the read operation.
 * @return void
 */
function zw_teksttv_cleaner_check_database_error(string $context): void
{
    global $wpdb;

    if ($wpdb->last_error !== '') {
        WP_CLI::error('Database error while ' . $context . ': ' . $wpdb->last_error);
    }
}

/**
 * Find row counts grouped by exact metadata key.
 *
 * @param string        $table Table name.
 * @param array<string> $keys  Metadata keys.
 * @return array<string, int>
 */
function zw_teksttv_cleaner_get_meta_matches(string $table, array $keys): array
{
    if (empty($keys)) {
        return [];
    }

    global $wpdb;

    $placeholders = implode(', ', array_fill(0, count($keys), '%s'));
    $query = 'SELECT meta_key, COUNT(*) AS row_count FROM ' . $table .
    ' WHERE meta_key IN (' . $placeholders . ')' .
    ' GROUP BY meta_key ORDER BY meta_key';

    $rows = $wpdb->get_results(
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Table and placeholder list are trusted and generated locally.
        $wpdb->prepare($query, ...$keys),
        ARRAY_A
    );
    zw_teksttv_cleaner_check_database_error('reading metadata from ' . $table);

    $matches = [];
    foreach ($rows as $row) {
        $matches[(string) $row['meta_key']] = (int) $row['row_count'];
    }

    return $matches;
}

/**
 * Delete metadata rows by exact key through the WordPress metadata API.
 *
 * @param string        $meta_type Metadata type.
 * @param array<string> $keys      Keys to delete.
 * @return void
 */
function zw_teksttv_cleaner_delete_metadata(string $meta_type, array $keys): void
{
    foreach ($keys as $key) {
        delete_metadata($meta_type, 0, $key, '', true);
    }
}

/**
 * Find option names matching old ACF patterns or exact legacy names.
 *
 * @param array<string> $patterns    LIKE patterns.
 * @param array<string> $exact_names Exact option names.
 * @return array<string>
 */
function zw_teksttv_cleaner_get_option_names(array $patterns, array $exact_names): array
{
    if (empty($patterns) && empty($exact_names)) {
        return [];
    }

    global $wpdb;

    $conditions = [];
    $values = [];

    foreach ($patterns as $pattern) {
        $conditions[] = 'option_name LIKE %s';
        $values[] = $pattern;
    }

    if (!empty($exact_names)) {
        $placeholders = implode(', ', array_fill(0, count($exact_names), '%s'));
        $conditions[] = 'option_name IN (' . $placeholders . ')';
        $values = array_merge($values, $exact_names);
    }

    $query = 'SELECT option_name FROM ' . $wpdb->options .
    ' WHERE ' . implode(' OR ', $conditions) .
    ' ORDER BY option_name';

    $option_names = $wpdb->get_col(
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Conditions and placeholders are generated locally.
        $wpdb->prepare($query, ...$values)
    );
    zw_teksttv_cleaner_check_database_error('reading matching option names');

    return array_values(array_unique(array_map('strval', $option_names)));
}

/**
 * Delete option rows through the WordPress Options API.
 *
 * @param array<string> $option_names Option names.
 * @return void
 */
function zw_teksttv_cleaner_delete_options(array $option_names): void
{
    foreach ($option_names as $option_name) {
        delete_option($option_name);
    }
}

/**
 * Find ACF posts for known keys, including WordPress' __trashed slug variants.
 *
 * @param string        $post_type ACF post type.
 * @param array<string> $keys      ACF keys.
 * @return array<int, array<string, int|string>>
 */
function zw_teksttv_cleaner_get_acf_posts_for_keys(string $post_type, array $keys): array
{
    if (empty($keys)) {
        return [];
    }

    global $wpdb;

    $conditions = [];
    $values = [$post_type];

    foreach ($keys as $key) {
        $conditions[] = '(post_name = %s OR post_name LIKE %s)';
        $values[] = $key;
        $values[] = $wpdb->esc_like($key . '__trashed') . '%';
    }

    $query = 'SELECT ID, post_type, post_name, post_parent FROM ' . $wpdb->posts .
    ' WHERE post_type = %s AND (' . implode(' OR ', $conditions) . ')';

    $rows = $wpdb->get_results(
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Conditions and placeholders are generated locally.
        $wpdb->prepare($query, ...$values),
        ARRAY_A
    );
    zw_teksttv_cleaner_check_database_error('reading ' . $post_type . ' posts');

    return $rows;
}

/**
 * Add all child ACF field posts to a set of matched posts.
 *
 * @param array<int, array<string, int|string>> $posts Initial ACF posts.
 * @return array<int, array<string, int|string>>
 */
function zw_teksttv_cleaner_collect_child_acf_posts(array $posts): array
{
    if (empty($posts)) {
        return [];
    }

    global $wpdb;

    $posts_by_id = [];
    foreach ($posts as $post) {
        $posts_by_id[(int) $post['ID']] = $post;
    }

    $pending = array_keys($posts_by_id);

    while (!empty($pending)) {
        $placeholders = implode(', ', array_fill(0, count($pending), '%d'));
        $query = 'SELECT ID, post_type, post_name, post_parent FROM ' . $wpdb->posts .
        ' WHERE post_type = %s AND post_parent IN (' . $placeholders . ')';

        $children = $wpdb->get_results(
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- The placeholder list is generated from trusted post IDs.
            $wpdb->prepare($query, 'acf-field', ...$pending),
            ARRAY_A
        );
        zw_teksttv_cleaner_check_database_error('reading child ACF field posts');

        $pending = [];
        foreach ($children as $child) {
            $child_id = (int) $child['ID'];
            if (!isset($posts_by_id[$child_id])) {
                $posts_by_id[$child_id] = $child;
                $pending[] = $child_id;
            }
        }
    }

    ksort($posts_by_id);

    return array_values($posts_by_id);
}

/**
 * Get ACF field group and field posts for known keys.
 *
 * @param array<string> $field_group_keys Field group keys.
 * @param array<string> $field_keys       Field keys.
 * @return array<int, array<string, int|string>>
 */
function zw_teksttv_cleaner_get_acf_posts(array $field_group_keys, array $field_keys): array
{
    $posts = array_merge(
        zw_teksttv_cleaner_get_acf_posts_for_keys('acf-field-group', $field_group_keys),
        zw_teksttv_cleaner_get_acf_posts_for_keys('acf-field', $field_keys)
    );

    return zw_teksttv_cleaner_collect_child_acf_posts($posts);
}

/**
 * Find which previously targeted ACF post IDs still exist.
 *
 * @param array<int> $post_ids Post IDs.
 * @return array<int, array<string, int|string>>
 */
function zw_teksttv_cleaner_get_acf_posts_by_id(array $post_ids): array
{
    if (empty($post_ids)) {
        return [];
    }

    global $wpdb;

    $placeholders = implode(', ', array_fill(0, count($post_ids), '%d'));
    $query = 'SELECT ID, post_type, post_name, post_parent FROM ' . $wpdb->posts .
    ' WHERE ID IN (' . $placeholders . ')';

    $rows = $wpdb->get_results(
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- The placeholder list is generated from trusted post IDs.
        $wpdb->prepare($query, ...$post_ids),
        ARRAY_A
    );
    zw_teksttv_cleaner_check_database_error('verifying deleted ACF posts');

    return $rows;
}

/**
 * Calculate an ACF field's depth in the selected post tree.
 *
 * @param array<string, int|string>                     $post        ACF post.
 * @param array<int, array<string, int|string>>         $posts_by_id ACF posts keyed by ID.
 * @return int
 */
function zw_teksttv_cleaner_get_acf_post_depth(array $post, array $posts_by_id): int
{
    $depth = 0;
    $parent_id = (int) $post['post_parent'];

    while ($parent_id > 0 && isset($posts_by_id[$parent_id])) {
        $depth++;
        $parent_id = (int) $posts_by_id[$parent_id]['post_parent'];
    }

    return $depth;
}

/**
 * Delete ACF fields child-first and field groups through ACF's APIs.
 *
 * @param array<int, array<string, int|string>> $posts ACF posts.
 * @return array<string>
 */
function zw_teksttv_cleaner_delete_acf_posts(array $posts): array
{
    if (empty($posts)) {
        return [];
    }

    $posts_by_id = [];
    $fields = [];
    $groups = [];

    foreach ($posts as $post) {
        $posts_by_id[(int) $post['ID']] = $post;

        if ($post['post_type'] === 'acf-field') {
            $fields[] = $post;
        } elseif ($post['post_type'] === 'acf-field-group') {
            $groups[] = $post;
        }
    }

    usort(
        $fields,
        static function (array $left, array $right) use ($posts_by_id): int {
            $depth_comparison = zw_teksttv_cleaner_get_acf_post_depth($right, $posts_by_id) <=> zw_teksttv_cleaner_get_acf_post_depth($left, $posts_by_id);

            return $depth_comparison !== 0 ? $depth_comparison : (int) $right['ID'] <=> (int) $left['ID'];
        }
    );

    $failures = [];

    foreach ($fields as $field) {
        if (!acf_delete_field((int) $field['ID']) && get_post((int) $field['ID']) !== null) {
            $failures[] = 'ACF field post ' . $field['ID'];
        }
    }

    foreach ($groups as $group) {
        if (!acf_delete_field_group((int) $group['ID']) && get_post((int) $group['ID']) !== null) {
            $failures[] = 'ACF field group post ' . $group['ID'];
        }
    }

    return $failures;
}

/**
 * Log all rows selected for cleanup without exposing stored values.
 *
 * @param array<int, array<string, int|string>> $acf_posts        ACF posts.
 * @param array<string, int>                    $post_meta_matches Postmeta keys and counts.
 * @param array<string, int>                    $term_meta_matches Termmeta keys and counts.
 * @param array<string>                         $option_names      Option names.
 * @return void
 */
function zw_teksttv_cleaner_log_targets(
    array $acf_posts,
    array $post_meta_matches,
    array $term_meta_matches,
    array $option_names
): void {
    WP_CLI::log('Matched ACF posts:');
    foreach ($acf_posts as $post) {
        WP_CLI::log('  - ID ' . $post['ID'] . ': ' . $post['post_type'] . ' ' . $post['post_name']);
    }
    if (empty($acf_posts)) {
        WP_CLI::log('  - none');
    }

    WP_CLI::log('Matched postmeta keys:');
    foreach ($post_meta_matches as $key => $count) {
        WP_CLI::log('  - ' . $key . ' (' . $count . ' rows)');
    }
    if (empty($post_meta_matches)) {
        WP_CLI::log('  - none');
    }

    WP_CLI::log('Matched termmeta keys:');
    foreach ($term_meta_matches as $key => $count) {
        WP_CLI::log('  - ' . $key . ' (' . $count . ' rows)');
    }
    if (empty($term_meta_matches)) {
        WP_CLI::log('  - none');
    }

    WP_CLI::log('Matched option names:');
    foreach ($option_names as $option_name) {
        WP_CLI::log('  - ' . $option_name);
    }
    if (empty($option_names)) {
        WP_CLI::log('  - none');
    }
}

global $wpdb;

$post_meta_keys = zw_teksttv_cleaner_with_reference_keys($post_meta_fields);
$term_meta_keys = zw_teksttv_cleaner_with_reference_keys($term_meta_fields);
$acf_posts = zw_teksttv_cleaner_get_acf_posts($field_group_keys, $field_keys);
$post_meta_matches = zw_teksttv_cleaner_get_meta_matches($wpdb->postmeta, $post_meta_keys);
$term_meta_matches = zw_teksttv_cleaner_get_meta_matches($wpdb->termmeta, $term_meta_keys);
$option_names = zw_teksttv_cleaner_get_option_names($acf_option_like_patterns, $legacy_option_names);

$acf_post_count = count($acf_posts);
$post_meta_count = array_sum($post_meta_matches);
$term_meta_count = array_sum($term_meta_matches);
$option_count = count($option_names);

WP_CLI::log('Found ACF field group/field posts: ' . $acf_post_count);
WP_CLI::log('Found postmeta rows: ' . $post_meta_count);
WP_CLI::log('Found termmeta rows: ' . $term_meta_count);
WP_CLI::log('Found option rows: ' . $option_count);

zw_teksttv_cleaner_log_targets($acf_posts, $post_meta_matches, $term_meta_matches, $option_names);

if (!empty($acf_posts) && (!function_exists('acf_delete_field') || !function_exists('acf_delete_field_group'))) {
    $message = 'Secure Custom Fields or Advanced Custom Fields must be active to delete ACF posts safely.';
    if ($dry_run) {
        WP_CLI::warning($message . ' Activate it before running the delete step.');
    } else {
        WP_CLI::error($message);
    }
}

if ($dry_run) {
    WP_CLI::success('Dry run complete. Review every target above, then add "delete" to remove these rows.');
    return;
}

$failures = zw_teksttv_cleaner_delete_acf_posts($acf_posts);
zw_teksttv_cleaner_delete_metadata('post', array_keys($post_meta_matches));
zw_teksttv_cleaner_delete_metadata('term', array_keys($term_meta_matches));
zw_teksttv_cleaner_delete_options($option_names);

$remaining_acf_posts = zw_teksttv_cleaner_get_acf_posts_by_id(array_map('intval', array_column($acf_posts, 'ID')));
$remaining_post_meta = zw_teksttv_cleaner_get_meta_matches($wpdb->postmeta, $post_meta_keys);
$remaining_term_meta = zw_teksttv_cleaner_get_meta_matches($wpdb->termmeta, $term_meta_keys);
$remaining_options = zw_teksttv_cleaner_get_option_names([], $option_names);

$remaining_acf_count = count($remaining_acf_posts);
$remaining_post_meta_count = array_sum($remaining_post_meta);
$remaining_term_meta_count = array_sum($remaining_term_meta);
$remaining_option_count = count($remaining_options);

$deleted_acf_count = max(0, $acf_post_count - $remaining_acf_count);
$deleted_post_meta_count = max(0, $post_meta_count - $remaining_post_meta_count);
$deleted_term_meta_count = max(0, $term_meta_count - $remaining_term_meta_count);
$deleted_option_count = max(0, $option_count - $remaining_option_count);

WP_CLI::log(
    'ACF posts: found ' . $acf_post_count .
    ', deleted ' . $deleted_acf_count .
    ', remaining ' . $remaining_acf_count
);
WP_CLI::log(
    'Postmeta rows: found ' . $post_meta_count .
    ', deleted ' . $deleted_post_meta_count .
    ', remaining ' . $remaining_post_meta_count
);
WP_CLI::log(
    'Termmeta rows: found ' . $term_meta_count .
    ', deleted ' . $deleted_term_meta_count .
    ', remaining ' . $remaining_term_meta_count
);
WP_CLI::log(
    'Option rows: found ' . $option_count .
    ', deleted ' . $deleted_option_count .
    ', remaining ' . $remaining_option_count
);

if ($remaining_acf_count > 0) {
    $failures[] = $remaining_acf_count . ' ACF posts remain';
}
if ($remaining_post_meta_count > 0) {
    $failures[] = $remaining_post_meta_count . ' postmeta rows remain';
}
if ($remaining_term_meta_count > 0) {
    $failures[] = $remaining_term_meta_count . ' termmeta rows remain';
}
if ($remaining_option_count > 0) {
    $failures[] = $remaining_option_count . ' option rows remain';
}

$failures = array_values(array_unique($failures));
if (!empty($failures)) {
    foreach ($failures as $failure) {
        WP_CLI::warning('Cleanup failure: ' . $failure);
    }

    WP_CLI::error('Tekst TV cleanup incomplete. Resolve the failures above and rerun the command.');
}

WP_CLI::success('Tekst TV cleanup completed and post-delete verification found no remaining targets.');
