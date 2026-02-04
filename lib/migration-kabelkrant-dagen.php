<?php

/**
 * =============================================================================
 * TODO: VERWIJDER DIT BESTAND NA MIGRATIE!
 * =============================================================================
 *
 * Dit script migreert de `post_kabelkrant_dagen` ACF velden van Nederlandse
 * afkortingen ("ma", "di", "wo", etc.) naar numerieke waarden ("1", "2", "3", etc.)
 *
 * HOE TE GEBRUIKEN:
 * 1. Deploy dit bestand naar productie
 * 2. Ga naar: /wp-admin/admin.php?page=migrate-kabelkrant-dagen
 * 3. Klik op "Start Migratie"
 * 4. Controleer of alles werkt
 * 5. VERWIJDER DIT BESTAND en de require in functions.php
 *
 * VERWIJDER DIT BESTAND ZODRA DE MIGRATIE COMPLEET IS!
 * =============================================================================
 */

namespace Streekomroep\Migration;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class KabelkrantDagenMigration
{
    private const OPTION_KEY = 'streekomroep_kabelkrant_dagen_migrated';
    private const META_KEY = 'post_kabelkrant_dagen';

    // Mapping from Dutch abbreviations to numeric values (ISO-8601: 1=Mon, 7=Sun)
    private const DAY_MAPPING = [
        'ma' => '1',
        'di' => '2',
        'wo' => '3',
        'do' => '4',
        'vr' => '5',
        'vrij' => '5',
        'za' => '6',
        'zo' => '7',
    ];

    public function __construct()
    {
        add_action('admin_menu', [$this, 'add_admin_page']);
        add_action('admin_init', [$this, 'handle_migration']);
    }

    public function add_admin_page(): void
    {
        add_management_page(
            'Migratie: Kabelkrant Dagen',
            'Kabelkrant Dagen Migratie',
            'manage_options',
            'migrate-kabelkrant-dagen',
            [$this, 'render_admin_page']
        );
    }

    public function render_admin_page(): void
    {
        $is_migrated = get_option(self::OPTION_KEY, false);
        $stats = $this->get_migration_stats();
        ?>
        <div class="wrap">
            <h1>Migratie: Kabelkrant Dagen</h1>

            <div style="background: #fff3cd; border: 1px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 4px;">
                <strong>⚠️ TODO:</strong> Verwijder <code>lib/migration-kabelkrant-dagen.php</code> en de require in
                <code>functions.php</code> zodra de migratie compleet is!
            </div>

            <?php if ($is_migrated): ?>
                <div style="background: #d4edda; border: 1px solid #28a745; padding: 15px; margin: 20px 0; border-radius: 4px;">
                    <strong>✅ Migratie voltooid!</strong><br>
                    De migratie is al uitgevoerd op: <?php echo esc_html(get_option(self::OPTION_KEY)); ?>
                </div>

                <form method="post" style="margin-top: 20px;">
                    <?php wp_nonce_field('kabelkrant_dagen_migration', 'migration_nonce'); ?>
                    <input type="hidden" name="action" value="reset_migration">
                    <button type="submit" class="button" onclick="return confirm('Weet je zeker dat je de migratie wilt resetten?');">
                        Reset Migratie Status
                    </button>
                </form>
            <?php else: ?>
                <h2>Status</h2>
                <table class="widefat" style="max-width: 500px;">
                    <tr>
                        <td>Posts met kabelkrant dagen:</td>
                        <td><strong><?php echo esc_html($stats['total']); ?></strong></td>
                    </tr>
                    <tr>
                        <td>Te migreren (oude format):</td>
                        <td><strong><?php echo esc_html($stats['needs_migration']); ?></strong></td>
                    </tr>
                    <tr>
                        <td>Al correct (numeriek):</td>
                        <td><strong><?php echo esc_html($stats['already_correct']); ?></strong></td>
                    </tr>
                </table>

                <?php if ($stats['needs_migration'] > 0): ?>
                    <h2>Preview (eerste 10)</h2>
                    <table class="widefat" style="max-width: 800px;">
                        <thead>
                            <tr>
                                <th>Post ID</th>
                                <th>Titel</th>
                                <th>Huidige waarde</th>
                                <th>Na migratie</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($stats['preview'] as $item): ?>
                                <tr>
                                    <td><?php echo esc_html($item['id']); ?></td>
                                    <td><?php echo esc_html($item['title']); ?></td>
                                    <td><code><?php echo esc_html(implode(', ', $item['old'])); ?></code></td>
                                    <td><code><?php echo esc_html(implode(', ', $item['new'])); ?></code></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <form method="post" style="margin-top: 20px;">
                        <?php wp_nonce_field('kabelkrant_dagen_migration', 'migration_nonce'); ?>
                        <input type="hidden" name="action" value="run_migration">
                        <button type="submit" class="button button-primary button-hero">
                            🚀 Start Migratie
                        </button>
                    </form>
                <?php else: ?>
                    <p style="color: green;"><strong>✅ Alle waarden zijn al in het correcte formaat!</strong></p>
                    <form method="post" style="margin-top: 20px;">
                        <?php wp_nonce_field('kabelkrant_dagen_migration', 'migration_nonce'); ?>
                        <input type="hidden" name="action" value="mark_complete">
                        <button type="submit" class="button button-primary">
                            Markeer als Voltooid
                        </button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
    }

    public function handle_migration(): void
    {
        if (!isset($_POST['migration_nonce']) || !wp_verify_nonce($_POST['migration_nonce'], 'kabelkrant_dagen_migration')) {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        $action = $_POST['action'] ?? '';

        switch ($action) {
            case 'run_migration':
                $this->run_migration();
                break;
            case 'mark_complete':
                update_option(self::OPTION_KEY, current_time('mysql'));
                add_settings_error('kabelkrant_migration', 'success', 'Migratie gemarkeerd als voltooid.', 'success');
                break;
            case 'reset_migration':
                delete_option(self::OPTION_KEY);
                add_settings_error('kabelkrant_migration', 'success', 'Migratie status gereset.', 'success');
                break;
        }
    }

    private function get_migration_stats(): array
    {
        global $wpdb;

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s",
            self::META_KEY
        ));

        $stats = [
            'total' => count($results),
            'needs_migration' => 0,
            'already_correct' => 0,
            'preview' => [],
        ];

        foreach ($results as $row) {
            $value = maybe_unserialize($row->meta_value);

            if (!is_array($value) || empty($value)) {
                continue;
            }

            $needs_migration = $this->needs_migration($value);

            if ($needs_migration) {
                $stats['needs_migration']++;

                if (count($stats['preview']) < 10) {
                    $stats['preview'][] = [
                        'id' => $row->post_id,
                        'title' => get_the_title($row->post_id),
                        'old' => $value,
                        'new' => $this->convert_days($value),
                    ];
                }
            } else {
                $stats['already_correct']++;
            }
        }

        return $stats;
    }

    private function needs_migration(array $days): bool
    {
        foreach ($days as $day) {
            $day_lower = strtolower(trim($day));
            if (isset(self::DAY_MAPPING[$day_lower])) {
                return true;
            }
        }
        return false;
    }

    private function convert_days(array $days): array
    {
        return array_map(function ($day) {
            $day_lower = strtolower(trim($day));
            return self::DAY_MAPPING[$day_lower] ?? $day;
        }, $days);
    }

    private function run_migration(): void
    {
        global $wpdb;

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s",
            self::META_KEY
        ));

        $migrated = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($results as $row) {
            $value = maybe_unserialize($row->meta_value);

            if (!is_array($value) || empty($value)) {
                $skipped++;
                continue;
            }

            if (!$this->needs_migration($value)) {
                $skipped++;
                continue;
            }

            $new_value = $this->convert_days($value);
            $updated = update_post_meta($row->post_id, self::META_KEY, $new_value);

            if ($updated) {
                $migrated++;
            } else {
                $errors++;
            }
        }

        update_option(self::OPTION_KEY, current_time('mysql'));

        add_settings_error(
            'kabelkrant_migration',
            'success',
            sprintf(
                'Migratie voltooid! %d posts gemigreerd, %d overgeslagen, %d errors.',
                $migrated,
                $skipped,
                $errors
            ),
            $errors > 0 ? 'warning' : 'success'
        );
    }
}

// Initialize the migration
new KabelkrantDagenMigration();
