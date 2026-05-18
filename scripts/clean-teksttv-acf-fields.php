<?php
/**
 * Clean Tekst TV ACF field definitions and stored values.
 *
 * Usage:
 *   wp eval-file scripts/clean-teksttv-acf-fields.php
 *   wp eval-file scripts/clean-teksttv-acf-fields.php delete
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
    'post_kabelkrant_datum_uit',
    'post_kabelkrant_extra_afbeeldingen',
];

$term_meta_fields = [
    'teksttv_categorie_afbeelding',
];

$option_like_patterns = [
    'options_teksttv\_%\_teksttv\_blokken%',
    '\_options\_teksttv\_%\_teksttv\_blokken%',
    'teksttv\_%\_teksttv\_blokken%',
    '\_teksttv\_%\_teksttv\_blokken%',
    'options\_teksttv\_blokken%',
    '\_options\_teksttv\_blokken%',
    'teksttv\_blokken%',
    '\_teksttv\_blokken%',
    'options_teksttv\_%\_teksttv\_ticker%',
    '\_options\_teksttv\_%\_teksttv\_ticker%',
    'teksttv\_%\_teksttv\_ticker%',
    '\_teksttv\_%\_teksttv\_ticker%',
    'options\_teksttv\_ticker%',
    '\_options\_teksttv\_ticker%',
    'teksttv\_ticker%',
    '\_teksttv\_ticker%',
    'options_teksttv\_%\_teksttv\_reclame%',
    '\_options\_teksttv\_%\_teksttv\_reclame%',
    'teksttv\_%\_teksttv\_reclame%',
    '\_teksttv\_%\_teksttv\_reclame%',
    'options\_teksttv\_reclame%',
    '\_options\_teksttv\_reclame%',
    'teksttv\_reclame%',
    '\_teksttv\_reclame%',
    'options\_teksttv\_instellingen\_openweather\_api\_key',
    '\_options\_teksttv\_instellingen\_openweather\_api\_key',
    'teksttv\_instellingen\_openweather\_api\_key',
    '\_teksttv\_instellingen\_openweather\_api\_key',
    'options\_openweather\_api\_key',
    '\_options\_openweather\_api\_key',
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
 * Count rows by exact key.
 *
 * @param string        $table  Table name.
 * @param string        $column Column name.
 * @param array<string> $keys   Keys to count.
 * @return int
 */
function zw_teksttv_cleaner_count_exact(string $table, string $column, array $keys): int
{
    if (empty($keys)) {
        return 0;
    }

    global $wpdb;

    $placeholders = implode(', ', array_fill(0, count($keys), '%s'));
    $query = 'SELECT COUNT(*) FROM ' . $table . ' WHERE ' . $column . ' IN (' . $placeholders . ')';

    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- The placeholder list is generated from trusted keys.
    return (int) $wpdb->get_var($wpdb->prepare($query, ...$keys));
}

/**
 * Delete rows by exact key.
 *
 * @param string        $table  Table name.
 * @param string        $column Column name.
 * @param array<string> $keys   Keys to delete.
 * @return int
 */
function zw_teksttv_cleaner_delete_exact(string $table, string $column, array $keys): int
{
    global $wpdb;

    $deleted = 0;
    foreach ($keys as $key) {
        $deleted += (int) $wpdb->delete($table, [$column => $key], ['%s']);
    }

    return $deleted;
}

/**
 * Find option names matching one of the old Tekst TV ACF option prefixes.
 *
 * @param array<string> $patterns LIKE patterns.
 * @return array<string>
 */
function zw_teksttv_cleaner_get_option_names(array $patterns): array
{
    global $wpdb;

    $option_names = [];
    foreach ($patterns as $pattern) {
        $matches = $wpdb->get_col(
            $wpdb->prepare(
                'SELECT option_name FROM ' . $wpdb->options . ' WHERE option_name LIKE %s',
                $pattern
            )
        );

        $option_names = array_merge($option_names, $matches);
    }

    return array_values(array_unique($option_names));
}

/**
 * Delete option rows by option name.
 *
 * @param array<string> $option_names Option names.
 * @return int
 */
function zw_teksttv_cleaner_delete_options(array $option_names): int
{
    global $wpdb;

    $deleted = 0;
    foreach ($option_names as $option_name) {
        $deleted += (int) $wpdb->delete($wpdb->options, ['option_name' => $option_name], ['%s']);
    }

    return $deleted;
}

/**
 * Get ACF field group and field post IDs for known keys.
 *
 * @param array<string> $field_group_keys Field group keys.
 * @param array<string> $field_keys       Field keys.
 * @return array<int>
 */
function zw_teksttv_cleaner_get_acf_post_ids(array $field_group_keys, array $field_keys): array
{
    global $wpdb;

    $post_ids = [];
    $post_names = array_merge($field_group_keys, $field_keys);

    if (!empty($post_names)) {
        $placeholders = implode(', ', array_fill(0, count($post_names), '%s'));
        $query = 'SELECT ID FROM ' . $wpdb->posts .
        ' WHERE post_type IN (%s, %s)' .
        ' AND post_name IN (' . $placeholders . ')';

        $post_ids = $wpdb->get_col(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- The placeholder list is generated from trusted keys.
                $query,
                'acf-field-group',
                'acf-field',
                ...$post_names
            )
        );
    }

    return zw_teksttv_cleaner_collect_child_acf_post_ids(array_map('intval', $post_ids));
}

/**
 * Recursively collect child ACF field posts.
 *
 * @param array<int> $post_ids Parent post IDs.
 * @return array<int>
 */
function zw_teksttv_cleaner_collect_child_acf_post_ids(array $post_ids): array
{
    if (empty($post_ids)) {
        return [];
    }

    global $wpdb;

    $all_ids = array_values(array_unique(array_map('intval', $post_ids)));
    $pending = $all_ids;

    while (!empty($pending)) {
        $placeholders = implode(', ', array_fill(0, count($pending), '%d'));
        $query = 'SELECT ID FROM ' . $wpdb->posts .
        ' WHERE post_type = %s' .
        ' AND post_parent IN (' . $placeholders . ')';

        $children = $wpdb->get_col(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- The placeholder list is generated from trusted post IDs.
                $query,
                'acf-field',
                ...$pending
            )
        );

        $children = array_values(array_diff(array_map('intval', $children), $all_ids));
        $all_ids = array_merge($all_ids, $children);
        $pending = $children;
    }

    rsort($all_ids);

    return $all_ids;
}

/**
 * Delete ACF posts permanently.
 *
 * @param array<int> $post_ids Post IDs.
 * @return int
 */
function zw_teksttv_cleaner_delete_acf_posts(array $post_ids): int
{
    $deleted = 0;

    foreach ($post_ids as $post_id) {
        if (wp_delete_post($post_id, true) instanceof WP_Post) {
            $deleted++;
        }
    }

    return $deleted;
}

$post_meta_keys = zw_teksttv_cleaner_with_reference_keys($post_meta_fields);
$term_meta_keys = zw_teksttv_cleaner_with_reference_keys($term_meta_fields);
$option_names = zw_teksttv_cleaner_get_option_names($option_like_patterns);
$acf_post_ids = zw_teksttv_cleaner_get_acf_post_ids($field_group_keys, $field_keys);

global $wpdb;

$post_meta_count = zw_teksttv_cleaner_count_exact($wpdb->postmeta, 'meta_key', $post_meta_keys);
$term_meta_count = zw_teksttv_cleaner_count_exact($wpdb->termmeta, 'meta_key', $term_meta_keys);
$option_count = count($option_names);
$acf_post_count = count($acf_post_ids);

WP_CLI::log('Found ACF field group/field posts: ' . $acf_post_count);
WP_CLI::log('Found postmeta rows: ' . $post_meta_count);
WP_CLI::log('Found termmeta rows: ' . $term_meta_count);
WP_CLI::log('Found options rows: ' . $option_count);

if ($dry_run) {
    WP_CLI::success('Dry run complete. Add "delete" to remove these rows.');
    return;
}

$deleted_acf_posts = zw_teksttv_cleaner_delete_acf_posts($acf_post_ids);
$deleted_post_meta = zw_teksttv_cleaner_delete_exact($wpdb->postmeta, 'meta_key', $post_meta_keys);
$deleted_term_meta = zw_teksttv_cleaner_delete_exact($wpdb->termmeta, 'meta_key', $term_meta_keys);
$deleted_options = zw_teksttv_cleaner_delete_options($option_names);

WP_CLI::success(
    'Deleted ACF posts: ' . $deleted_acf_posts .
    ', postmeta rows: ' . $deleted_post_meta .
    ', termmeta rows: ' . $deleted_term_meta .
    ', options rows: ' . $deleted_options
);
