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
 *   3. wp option delete zw_fm_makers_migration_done zw_fm_makers_migration_lock \
 *        zw_fm_makers_migration_report zw_fm_makers_migration_attempts
 *   4. wp db query "DELETE FROM wp_postmeta WHERE meta_key = '_zw_fm_makers_retry'"
 */

// Old field, dropped from the field group but still present in the database.
const ZW_FM_MAKERS_OLD_META = 'fm_show_presentator';

// Marks a show whose makers were left behind by a failed attempt, so a retry knows those rows are
// ours to redo instead of mistaking them for makers that were already there.
const ZW_FM_MAKERS_RETRY_META = '_zw_fm_makers_retry';

// Field keys from streekomroep-acf-json/group_5f21b1dcb2dc2.json.
const ZW_FM_MAKERS_FIELD = 'field_687a3f5e0c1a1';
const ZW_FM_MAKERS_FIELD_NAAM = 'field_687a3f5e0c1a2';
const ZW_FM_MAKERS_FIELD_BIO = 'field_687a3f5e0c1a3';
const ZW_FM_MAKERS_FIELD_FOTO = 'field_687a3f5e0c1a4';

const ZW_FM_MAKERS_OPTION_DONE = 'zw_fm_makers_migration_done';
const ZW_FM_MAKERS_OPTION_LOCK = 'zw_fm_makers_migration_lock';
const ZW_FM_MAKERS_OPTION_REPORT = 'zw_fm_makers_migration_report';
const ZW_FM_MAKERS_OPTION_ATTEMPTS = 'zw_fm_makers_migration_attempts';

// Seconds before a lock left behind by a crashed run is considered stale.
const ZW_FM_MAKERS_LOCK_TIMEOUT = 300;

// How often a run that hit recoverable errors may be repeated before giving up.
const ZW_FM_MAKERS_MAX_ATTEMPTS = 3;

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

    if (
        !function_exists('acf_get_field')
        || !function_exists('update_field')
        || !function_exists('delete_field')
    ) {
        return;
    }

    if (!zw_fm_makers_claim_lock()) {
        return;
    }

    $report = zw_fm_makers_migrate();

    // The whole run failed, for instance because the database was unreachable. Keep the lock, so
    // the next attempt has to wait for it to go stale instead of hammering a broken database, and
    // flag it, because an aborted run would otherwise leave nothing at all behind in the admin.
    if ($report === null) {
        $aborted = zw_fm_makers_stored_report();
        $aborted['aborted'] = 1;
        update_option(ZW_FM_MAKERS_OPTION_REPORT, $aborted, false);
        return;
    }

    $report = zw_fm_makers_add_up($report);

    $attempts = (int) get_option(ZW_FM_MAKERS_OPTION_ATTEMPTS) + 1;
    update_option(ZW_FM_MAKERS_OPTION_ATTEMPTS, $attempts, false);

    // Recoverable failures earn another run on a later request, but only a few, so a show that
    // can never be converted does not keep retrying on every single admin page load.
    $retry = $report['failed_recoverable'] > 0 && $attempts < ZW_FM_MAKERS_MAX_ATTEMPTS;

    if ($retry) {
        zw_fm_makers_log(sprintf(
            'Attempt %d of %d left recoverable failures behind, will run again.',
            $attempts,
            ZW_FM_MAKERS_MAX_ATTEMPTS
        ));
    } else {
        update_option(ZW_FM_MAKERS_OPTION_DONE, gmdate('c'), false);
    }

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
        'failed_recoverable' => 0,
        'failed_unexpected' => 0,
        'unknown_users' => 0,
        'revisions' => 0,
        'aborted' => 0,
    ];
}

/**
 * The report of the previous attempts, with every counter present.
 *
 * @return array<string, int>
 */
function zw_fm_makers_stored_report(): array
{
    $stored = get_option(ZW_FM_MAKERS_OPTION_REPORT);

    return array_merge(zw_fm_makers_empty_report(), is_array($stored) ? $stored : []);
}

/**
 * Folds this run into what earlier attempts already reported.
 *
 * A retry only revisits the shows that still hold old meta, so counters for work that happened
 * once have to accumulate, while the problem counters describe what is still outstanding.
 *
 * @param array<string, int> $report
 * @return array<string, int>
 */
function zw_fm_makers_add_up(array $report): array
{
    $previous = zw_fm_makers_stored_report();

    foreach (['shows', 'makers', 'skipped', 'empty', 'revisions'] as $key) {
        $report[$key] += $previous[$key];
    }

    return $report;
}

/**
 * The repeater sub fields, keyed by ACF field key with the meta name they are stored under.
 *
 * @return array<string, string>
 */
function zw_fm_makers_sub_fields(): array
{
    return [
        ZW_FM_MAKERS_FIELD_NAAM => 'fm_show_maker_naam',
        ZW_FM_MAKERS_FIELD_BIO => 'fm_show_maker_bio',
        ZW_FM_MAKERS_FIELD_FOTO => 'fm_show_maker_foto',
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

    $posts = zw_fm_makers_find_posts();
    if ($posts === null) {
        return null;
    }

    $report = zw_fm_makers_empty_report();

    foreach ($posts as $post) {
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
            $report['failed_unexpected']++;
            continue;
        }

        // Never overwrite makers that were already filled in by hand or by an earlier run. Rows
        // that an unfinished attempt left behind are ours to redo, so those do not count.
        $ours = zw_fm_makers_is_claimed($post_id);

        if (!$ours && (int) get_post_meta($post_id, 'fm_show_makers', true) > 0) {
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

        // Keep the old meta whenever it holds data we could not convert, so nothing is lost
        // silently. get_userdata() returns false for a database error just as it does for a
        // deleted user, so this counts as recoverable and earns another attempt.
        if (empty($rows)) {
            zw_fm_makers_log(sprintf(
                'Post %d: none of the presenters (%s) could be resolved, kept the old meta.',
                $post_id,
                implode(', ', $user_ids)
            ));
            $report['failed_recoverable']++;
            continue;
        }

        // Claim the show before touching its repeater. Writing rows first would leave a window in
        // which a dying request abandons half a repeater that carries nothing to identify it, and
        // the next attempt would take those rows for makers that were already there.
        if (!zw_fm_makers_claim_post($post_id)) {
            zw_fm_makers_log('Post ' . $post_id . ': could not be claimed, left the repeater alone.');
            $report['failed_recoverable']++;
            continue;
        }

        // The return value says nothing useful here: it only reflects whether the stored row count
        // changed, which is false for a retry that writes the same number of rows again. What
        // actually landed in the table is what counts, so the read back below decides.
        update_field(ZW_FM_MAKERS_FIELD, $rows, $post_id);

        $mismatch = zw_fm_makers_verify($post_id, $rows);
        if ($mismatch !== null) {
            delete_field(ZW_FM_MAKERS_FIELD, $post_id);

            $left = zw_fm_makers_stored_meta($post_id);
            zw_fm_makers_log(sprintf(
                'Post %d: %s - %s, kept the old meta.',
                $post_id,
                $mismatch,
                $left === [] ? 'rolled the row back' : 'could not roll the row back'
            ));
            $report['failed_recoverable']++;
            continue;
        }

        zw_fm_makers_delete_old_meta($post_id);

        // The claim is only released once the source data is demonstrably gone, so a show is never
        // left looking finished while its presenters are still in the database.
        if (!zw_fm_makers_old_meta_gone($post_id)) {
            zw_fm_makers_log('Post ' . $post_id . ': makers are in place but the old meta stayed behind.');
            $report['failed_recoverable']++;
            continue;
        }

        delete_metadata('post', $post_id, ZW_FM_MAKERS_RETRY_META);
        $report['shows']++;
        $report['makers'] += count($rows);
        zw_fm_makers_log('Post ' . $post_id . ': migrated ' . count($rows) . ' maker(s): ' . implode(', ', $names) . '.');
    }

    zw_fm_makers_log(sprintf(
        'Done. %d show(s) with %d maker(s) migrated, %d skipped, %d without presenters, '
        . '%d recoverable failure(s), %d unexpected record(s), %d unknown user(s), '
        . '%d revision(s) cleaned.',
        $report['shows'],
        $report['makers'],
        $report['skipped'],
        $report['empty'],
        $report['failed_recoverable'],
        $report['failed_unexpected'],
        $report['unknown_users'],
        $report['revisions']
    ));

    return $report;
}

/**
 * Finds every post that still holds the old presenter meta, whatever its status.
 *
 * @return array<int, object>|null Rows with ID, post_type and post_status, or null on a query error.
 */
function zw_fm_makers_find_posts(): ?array
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

    // wpdb hands back an empty result set for a failed query as well, so without this check a
    // database error would look like a site that has nothing left to migrate.
    if ($wpdb->last_error !== '') {
        zw_fm_makers_log('Aborted: could not list the posts to migrate - ' . $wpdb->last_error);
        return null;
    }

    return is_array($rows) ? $rows : [];
}

/**
 * Checks that everything the migration meant to write actually reached the database.
 *
 * SCF stores a repeater as a series of separate metadata writes, ignores the result of every
 * single one of them, and reports success as long as the parent row count was stored. A row whose
 * name failed to save would therefore pass as migrated, after which the source data is deleted.
 * Reads straight from the table rather than through get_post_meta(), so no cache can mask it.
 *
 * @param array<int, array<string, mixed>> $rows
 * @return string|null Null when everything matches, otherwise the first mismatch found.
 */
function zw_fm_makers_verify(int $post_id, array $rows): ?string
{
    // Every value is stored alongside an underscore prefixed row holding the field key. ACF needs
    // the one for the repeater itself to recognise the field: without it get_field() hands back
    // the bare row count instead of the rows. update_metadata() unslashes before storing, so the
    // expected values go through the same transformation.
    $expected = [
        'fm_show_makers' => (string) count($rows),
        '_fm_show_makers' => ZW_FM_MAKERS_FIELD,
    ];

    foreach ($rows as $index => $row) {
        foreach (zw_fm_makers_sub_fields() as $field_key => $name) {
            $meta_key = 'fm_show_makers_' . $index . '_' . $name;
            $expected[$meta_key] = (string) wp_unslash($row[$field_key]);
            $expected['_' . $meta_key] = $field_key;
        }
    }

    $stored = zw_fm_makers_stored_meta($post_id);
    if ($stored === null) {
        return 'could not read the makers back';
    }

    foreach ($expected as $key => $value) {
        if (($stored[$key] ?? '') !== $value) {
            return sprintf(
                '%s was stored as "%s" instead of "%s"',
                $key,
                $stored[$key] ?? '',
                $value
            );
        }
    }

    return null;
}

/**
 * Marks a show as being migrated and confirms the mark is really stored.
 *
 * @return bool False when the claim could not be established, in which case the repeater of this
 *              show must be left alone.
 */
function zw_fm_makers_claim_post(int $post_id): bool
{
    // The return value of update_metadata() proves nothing: it is false both when the write failed
    // and when the mark was already there from an earlier attempt. Only a read back settles it.
    update_metadata('post', $post_id, ZW_FM_MAKERS_RETRY_META, 1);

    return zw_fm_makers_is_claimed($post_id);
}

/**
 * Whether the makers of a show were written by an attempt that never finished.
 */
function zw_fm_makers_is_claimed(int $post_id): bool
{
    global $wpdb;

    $value = $wpdb->get_var(
        $wpdb->prepare(
            'SELECT meta_value FROM ' . $wpdb->postmeta . ' WHERE post_id = %d AND meta_key = %s LIMIT 1',
            $post_id,
            ZW_FM_MAKERS_RETRY_META
        )
    );

    return $wpdb->last_error === '' && $value !== null;
}

/**
 * Whether the old presenter meta of a show is really gone from the table.
 */
function zw_fm_makers_old_meta_gone(int $post_id): bool
{
    global $wpdb;

    $left = $wpdb->get_var(
        $wpdb->prepare(
            'SELECT COUNT(*) FROM ' . $wpdb->postmeta . ' WHERE post_id = %d AND meta_key IN (%s, %s)',
            $post_id,
            ZW_FM_MAKERS_OLD_META,
            '_' . ZW_FM_MAKERS_OLD_META
        )
    );

    return $wpdb->last_error === '' && (int) $left === 0;
}

/**
 * Reads every makers row of a post straight from the table, values and field key references alike.
 *
 * @return array<string, string>|null Meta keyed by name, or null when the read itself failed.
 */
function zw_fm_makers_stored_meta(int $post_id): ?array
{
    global $wpdb;

    $found = $wpdb->get_results(
        $wpdb->prepare(
            'SELECT meta_key, meta_value FROM ' . $wpdb->postmeta
            . ' WHERE post_id = %d AND (meta_key LIKE %s OR meta_key LIKE %s)',
            $post_id,
            $wpdb->esc_like('fm_show_makers') . '%',
            $wpdb->esc_like('_fm_show_makers') . '%'
        )
    );

    if ($wpdb->last_error !== '') {
        zw_fm_makers_log('Post ' . $post_id . ': reading the makers back failed - ' . $wpdb->last_error);
        return null;
    }

    $stored = [];
    foreach ((array) $found as $row) {
        $stored[$row->meta_key] = $row->meta_value;
    }

    return $stored;
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
 * Shows the result of the migration once. This is an operational message rather than editorial
 * information, so it stays with the administrators who can act on it.
 */
function zw_fm_makers_render_notice(): void
{
    if (!current_user_can('manage_options')) {
        return;
    }

    if (!is_array(get_option(ZW_FM_MAKERS_OPTION_REPORT))) {
        return;
    }

    $report = zw_fm_makers_stored_report();

    if ($report['aborted'] > 0) {
        zw_fm_makers_print_notice(
            'notice-error',
            'De migratie kon niet draaien. Zie de PHP-log; er wordt automatisch opnieuw geprobeerd.'
        );
        return;
    }

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

    if ($report['failed_recoverable'] > 0) {
        $lines[] = 'Niet omgezet, zie de PHP-log: ' . $report['failed_recoverable'] . '.';
    }

    if ($report['failed_unexpected'] > 0) {
        $lines[] = 'Onverwachte records overgeslagen, zie de PHP-log: ' . $report['failed_unexpected'] . '.';
    }

    $problems = $report['failed_recoverable'] + $report['failed_unexpected'] + $report['unknown_users'];

    zw_fm_makers_print_notice($problems > 0 ? 'notice-warning' : 'notice-success', implode(' ', $lines));
}

/**
 * Prints one admin notice with a link that puts it away for good.
 */
function zw_fm_makers_print_notice(string $class, string $message): void
{
    $dismiss_url = wp_nonce_url(
        add_query_arg('zw_fm_makers_dismiss', '1'),
        'zw_fm_makers_dismiss'
    );

    printf(
        '<div class="notice %s"><p><strong>%s</strong> %s</p><p><a href="%s">%s</a></p></div>',
        esc_attr($class),
        esc_html('Migratie presentatoren naar makers:'),
        esc_html($message),
        esc_url($dismiss_url),
        esc_html('Melding verbergen')
    );
}

/**
 * Removes the stored report when the notice is dismissed.
 */
function zw_fm_makers_dismiss_notice(): void
{
    if (!isset($_GET['zw_fm_makers_dismiss']) || !current_user_can('manage_options')) {
        return;
    }

    check_admin_referer('zw_fm_makers_dismiss');
    delete_option(ZW_FM_MAKERS_OPTION_REPORT);

    wp_safe_redirect(remove_query_arg(['zw_fm_makers_dismiss', '_wpnonce']));
    exit;
}
