<?php

/**
 * TEMPORARY - one-time migration of FM presenters to the makers repeater.
 *
 * The redesigned single FM show page replaced the `fm_show_presentator` user field with the
 * `fm_show_makers` repeater (name, bio, photo). Existing sites still hold user IDs in the old
 * postmeta, which no ACF field reads anymore, so the makers block would render empty after the
 * upgrade. This migration copies those users into repeater rows and drops the obsolete meta.
 *
 * It runs once, on the first wp-admin request after the upgrade, and reports through the PHP
 * error log plus a dismissible admin notice.
 *
 * REMOVE AFTER THE UPGRADE HAS BEEN ROLLED OUT EVERYWHERE:
 *   1. delete this file
 *   2. drop the require_once in functions.php
 *   3. wp option delete zw_fm_makers_migration_done zw_fm_makers_migration_lock zw_fm_makers_migration_report
 */

// Old field, dropped from the field group but still present in the database.
const ZW_FM_MAKERS_OLD_META = 'fm_show_presentator';

// Field keys from streekomroep-acf-json/group_5f21b1dcb2dc2.json.
const ZW_FM_MAKERS_FIELD = 'field_687a3f5e0c1a1';
const ZW_FM_MAKERS_FIELD_NAAM = 'field_687a3f5e0c1a2';
const ZW_FM_MAKERS_FIELD_BIO = 'field_687a3f5e0c1a3';
const ZW_FM_MAKERS_FIELD_FOTO = 'field_687a3f5e0c1a4';

const ZW_FM_MAKERS_OPTION_DONE = 'zw_fm_makers_migration_done';
const ZW_FM_MAKERS_OPTION_LOCK = 'zw_fm_makers_migration_lock';
const ZW_FM_MAKERS_OPTION_REPORT = 'zw_fm_makers_migration_report';

// Seconds before a lock left behind by a crashed run is considered stale.
const ZW_FM_MAKERS_LOCK_TIMEOUT = 300;

add_action('admin_init', 'zw_fm_makers_dismiss_notice');
add_action('admin_init', 'zw_fm_makers_maybe_migrate');
add_action('admin_notices', 'zw_fm_makers_render_notice');

/**
 * Runs the migration once, on a regular wp-admin page load.
 */
function zw_fm_makers_maybe_migrate(): void
{
    if (wp_doing_ajax() || wp_doing_cron() || wp_installing()) {
        return;
    }

    if (get_option(ZW_FM_MAKERS_OPTION_DONE)) {
        return;
    }

    if (!function_exists('acf_get_field') || !function_exists('update_field')) {
        return;
    }

    if (!zw_fm_makers_claim_lock()) {
        return;
    }

    $report = zw_fm_makers_migrate();

    // The migration could not run. Keep the lock so the next attempt waits for it to go stale.
    if ($report === null) {
        return;
    }

    update_option(ZW_FM_MAKERS_OPTION_DONE, gmdate('c'), false);
    delete_option(ZW_FM_MAKERS_OPTION_LOCK);

    // Sites that never had presenters get no notice at all.
    if (array_sum($report) > 0) {
        update_option(ZW_FM_MAKERS_OPTION_REPORT, $report, false);
    }
}

/**
 * The counters the migration reports on.
 *
 * @return array<string, int>
 */
function zw_fm_makers_empty_report(): array
{
    return [
        'shows' => 0,
        'makers' => 0,
        'skipped' => 0,
        'empty' => 0,
        'failed' => 0,
        'unknown_users' => 0,
        'revisions' => 0,
    ];
}

/**
 * Claims the migration lock, so concurrent admin requests cannot run the migration twice.
 *
 * @return bool True when this request may run the migration.
 */
function zw_fm_makers_claim_lock(): bool
{
    if (add_option(ZW_FM_MAKERS_OPTION_LOCK, time(), '', false)) {
        return true;
    }

    $claimed_at = (int) get_option(ZW_FM_MAKERS_OPTION_LOCK);
    if ($claimed_at > time() - ZW_FM_MAKERS_LOCK_TIMEOUT) {
        return false;
    }

    // A previous run died before releasing the lock. Take it over.
    zw_fm_makers_log('Taking over a stale lock from ' . gmdate('c', $claimed_at) . '.');
    update_option(ZW_FM_MAKERS_OPTION_LOCK, time(), false);

    return true;
}

/**
 * Converts every fm_show_presentator value into fm_show_makers rows.
 *
 * @return array<string, int>|null Counters for the admin notice, or null when nothing could run.
 */
function zw_fm_makers_migrate(): ?array
{
    if (!acf_get_field(ZW_FM_MAKERS_FIELD)) {
        zw_fm_makers_log('Aborted: ACF field ' . ZW_FM_MAKERS_FIELD . ' is unknown. Is the field group JSON in place?');
        return null;
    }

    $report = zw_fm_makers_empty_report();

    foreach (zw_fm_makers_find_posts() as $post) {
        $post_id = (int) $post->ID;

        // Revisions carry their own copy of the old meta. There is nothing to migrate there.
        if ($post->post_type === 'revision') {
            zw_fm_makers_delete_old_meta($post_id);
            $report['revisions']++;
            continue;
        }

        if ($post->post_type !== 'fm' || $post->post_status === 'auto-draft') {
            zw_fm_makers_log(sprintf(
                'Post %d: skipped, unexpected post type "%s" with status "%s".',
                $post_id,
                $post->post_type,
                $post->post_status
            ));
            $report['failed']++;
            continue;
        }

        // Never overwrite makers that were already filled in by hand or by an earlier run.
        if ((int) get_post_meta($post_id, 'fm_show_makers', true) > 0) {
            zw_fm_makers_delete_old_meta($post_id);
            zw_fm_makers_log('Post ' . $post_id . ': already has makers, only removed the old meta.');
            $report['skipped']++;
            continue;
        }

        $user_ids = zw_fm_makers_read_old_meta($post_id);

        if (empty($user_ids)) {
            zw_fm_makers_delete_old_meta($post_id);
            zw_fm_makers_log('Post ' . $post_id . ': no presenters were set, only removed the old meta.');
            $report['empty']++;
            continue;
        }

        $rows = [];
        $names = [];
        foreach ($user_ids as $user_id) {
            $user = get_userdata($user_id);
            if (!$user) {
                zw_fm_makers_log('Post ' . $post_id . ': user ' . $user_id . ' no longer exists, skipped.');
                $report['unknown_users']++;
                continue;
            }

            $name = zw_fm_makers_name($user);
            $names[] = $name;
            $rows[] = [
                ZW_FM_MAKERS_FIELD_NAAM => $name,
                ZW_FM_MAKERS_FIELD_BIO => zw_fm_makers_bio($user),
                ZW_FM_MAKERS_FIELD_FOTO => zw_fm_makers_photo($user->ID),
            ];
        }

        // Keep the old meta whenever it holds data we could not convert, so nothing is lost silently.
        if (empty($rows)) {
            zw_fm_makers_log(sprintf(
                'Post %d: none of the presenters (%s) could be resolved, kept the old meta.',
                $post_id,
                implode(', ', $user_ids)
            ));
            $report['failed']++;
            continue;
        }

        if (!update_field(ZW_FM_MAKERS_FIELD, $rows, $post_id)) {
            zw_fm_makers_log('Post ' . $post_id . ': writing the makers failed, kept the old meta.');
            $report['failed']++;
            continue;
        }

        zw_fm_makers_delete_old_meta($post_id);
        $report['shows']++;
        $report['makers'] += count($rows);
        zw_fm_makers_log('Post ' . $post_id . ': migrated ' . count($rows) . ' maker(s): ' . implode(', ', $names) . '.');
    }

    zw_fm_makers_log(sprintf(
        'Done. %d show(s) with %d maker(s) migrated, %d skipped, %d without presenters, '
        . '%d failed, %d unknown user(s), %d revision(s) cleaned.',
        $report['shows'],
        $report['makers'],
        $report['skipped'],
        $report['empty'],
        $report['failed'],
        $report['unknown_users'],
        $report['revisions']
    ));

    return $report;
}

/**
 * Finds every post that still holds the old presenter meta, whatever its status.
 *
 * @return array<int, object> Rows with ID, post_type and post_status.
 */
function zw_fm_makers_find_posts(): array
{
    global $wpdb;

    $rows = $wpdb->get_results(
        $wpdb->prepare(
            'SELECT p.ID, p.post_type, p.post_status'
            . ' FROM ' . $wpdb->postmeta . ' pm'
            . ' INNER JOIN ' . $wpdb->posts . ' p ON p.ID = pm.post_id'
            . ' WHERE pm.meta_key = %s'
            . ' GROUP BY p.ID, p.post_type, p.post_status'
            . ' ORDER BY p.ID',
            ZW_FM_MAKERS_OLD_META
        )
    );

    return is_array($rows) ? $rows : [];
}

/**
 * Reads the old presenter meta as a list of user IDs.
 *
 * @return array<int, int>
 */
function zw_fm_makers_read_old_meta(int $post_id): array
{
    $value = get_post_meta($post_id, ZW_FM_MAKERS_OLD_META, true);

    if (!is_array($value)) {
        $value = $value === '' || $value === null ? [] : [$value];
    }

    return array_values(array_unique(array_filter(array_map('absint', $value))));
}

/**
 * Removes the old presenter meta and its ACF field reference.
 *
 * Goes through the raw metadata API on purpose: delete_post_meta() redirects revisions to their
 * parent post, which would wipe the meta of a show we deliberately left untouched.
 */
function zw_fm_makers_delete_old_meta(int $post_id): void
{
    delete_metadata('post', $post_id, ZW_FM_MAKERS_OLD_META);
    delete_metadata('post', $post_id, '_' . ZW_FM_MAKERS_OLD_META);
}

/**
 * Picks the name to show for a maker, matching what the old page rendered.
 */
function zw_fm_makers_name(WP_User $user): string
{
    $candidates = [
        $user->display_name,
        trim($user->first_name . ' ' . $user->last_name),
        $user->nickname,
        $user->user_login,
    ];

    foreach ($candidates as $candidate) {
        $candidate = trim((string) $candidate);
        if ($candidate !== '') {
            return $candidate;
        }
    }

    return '';
}

/**
 * Turns the WordPress profile biography into a single line of plain text.
 */
function zw_fm_makers_bio(WP_User $user): string
{
    $bio = wp_strip_all_tags((string) $user->description, true);
    $collapsed = preg_replace('/\s+/u', ' ', $bio);

    return trim($collapsed ?? $bio);
}

/**
 * Resolves the attachment ID of the profile photo set on the user.
 *
 * @return int|string The attachment ID, or an empty string when there is no usable photo.
 */
function zw_fm_makers_photo(int $user_id)
{
    $photo = get_field('gebruiker_profielfoto', 'user_' . $user_id);

    // The field returns an ID, but stay tolerant of the array and object return formats.
    if (is_array($photo)) {
        $photo = $photo['ID'] ?? $photo['id'] ?? 0;
    } elseif ($photo instanceof WP_Post) {
        $photo = $photo->ID;
    }

    $photo = (int) $photo;

    return $photo > 0 && wp_attachment_is_image($photo) ? $photo : '';
}

/**
 * Writes a line to the PHP error log.
 */
function zw_fm_makers_log(string $message): void
{
    error_log('[zw fm-makers migratie] ' . $message);
}

/**
 * Shows the result of the migration once, to anyone who can edit content.
 */
function zw_fm_makers_render_notice(): void
{
    if (!current_user_can('edit_posts')) {
        return;
    }

    $report = get_option(ZW_FM_MAKERS_OPTION_REPORT);
    if (!is_array($report)) {
        return;
    }

    $report = array_merge(zw_fm_makers_empty_report(), $report);

    // Counts vary, so keep the wording free of singular and plural verb forms.
    $count = function (int $number, string $singular, string $plural): string {
        return $number . ' ' . ($number === 1 ? $singular : $plural);
    };

    $summary = sprintf(
        '%s bijgewerkt met %s.',
        $count($report['shows'], 'FM-programma', 'FM-programma\'s'),
        $count($report['makers'], 'maker', 'makers')
    );
    $lines = [$summary];

    if ($report['skipped'] > 0) {
        $lines[] = 'Overgeslagen omdat er al makers stonden: ' . $report['skipped'] . '.';
    }

    if ($report['empty'] > 0) {
        $lines[] = 'Zonder presentatoren: ' . $report['empty'] . '.';
    }

    if ($report['unknown_users'] > 0) {
        $lines[] = 'Presentatoren die niet meer als gebruiker bestaan: ' . $report['unknown_users'] . '.';
    }

    if ($report['failed'] > 0) {
        $lines[] = 'Niet omgezet, zie de PHP-log: ' . $report['failed'] . '.';
    }

    $has_problems = $report['failed'] > 0 || $report['unknown_users'] > 0;
    $dismiss_url = wp_nonce_url(
        add_query_arg('zw_fm_makers_dismiss', '1'),
        'zw_fm_makers_dismiss'
    );

    printf(
        '<div class="notice %s"><p><strong>%s</strong> %s</p><p><a href="%s">%s</a></p></div>',
        $has_problems ? 'notice-warning' : 'notice-success',
        esc_html('Migratie presentatoren naar makers:'),
        esc_html(implode(' ', $lines)),
        esc_url($dismiss_url),
        esc_html('Melding verbergen')
    );
}

/**
 * Removes the stored report when the notice is dismissed.
 */
function zw_fm_makers_dismiss_notice(): void
{
    if (!isset($_GET['zw_fm_makers_dismiss']) || !current_user_can('edit_posts')) {
        return;
    }

    check_admin_referer('zw_fm_makers_dismiss');
    delete_option(ZW_FM_MAKERS_OPTION_REPORT);

    wp_safe_redirect(remove_query_arg(['zw_fm_makers_dismiss', '_wpnonce']));
    exit;
}
