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
 * 3. Klik op "Start Migratie" (verwerkt 500 posts per keer)
 * 4. Herhaal tot alles gemigreerd is
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
    private const BATCH_SIZE = 500;

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

            <?php if ($is_migrated && $stats['needs_migration'] === 0): ?>
                <div style="background: #d4edda; border: 1px solid #28a745; padding: 15px; margin: 20px 0; border-radius: 4px;">
                    <strong>✅ Migratie voltooid!</strong><br>
                    Alle <?php echo esc_html($stats['total']); ?> posts zijn gemigreerd.
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
                        <td><strong style="color: <?php echo $stats['needs_migration'] > 0 ? '#dc3545' : '#28a745'; ?>">
                            <?php echo esc_html($stats['needs_migration']); ?>
                        </strong></td>
                    </tr>
                    <tr>
                        <td>Al correct (numeriek):</td>
                        <td><strong style="color: #28a745;"><?php echo esc_html($stats['already_correct']); ?></strong></td>
                    </tr>
                </table>

                <?php if ($stats['needs_migration'] > 0): ?>
                    <div style="background: #e7f3ff; border: 1px solid #0073aa; padding: 15px; margin: 20px 0; border-radius: 4px;">
                        <strong>ℹ️ Batch migratie:</strong> Er worden <?php echo self::BATCH_SIZE; ?> posts per keer verwerkt.
                        Je moet mogelijk meerdere keren op de knop klikken.
                    </div>

                    <form method="post" style="margin-top: 20px;">
                        <?php wp_nonce_field('kabelkrant_dagen_migration', 'migration_nonce'); ?>
                        <input type="hidden" name="action" value="run_migration">
                        <button type="submit" class="button button-primary button-hero">
                            🚀 Migreer volgende <?php echo min(self::BATCH_SIZE, $stats['needs_migration']); ?> posts
                        </button>
                    </form>
                <?php else: ?>
                    <div style="background: #d4edda; border: 1px solid #28a745; padding: 15px; margin: 20px 0; border-radius: 4px;">
                        <strong>✅ Alle posts zijn al in het correcte formaat!</strong>
                    </div>
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

        // Count total posts with this meta key
        $total = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s",
            self::META_KEY
        ));

        // Count posts that need migration (contain Dutch abbreviations)
        $needs_migration = 0;
        $already_correct = 0;

        // Process in chunks to avoid memory issues
        $offset = 0;
        $chunk_size = 1000;

        while ($offset < $total) {
            $results = $wpdb->get_results($wpdb->prepare(
                "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s LIMIT %d OFFSET %d",
                self::META_KEY,
                $chunk_size,
                $offset
            ));

            foreach ($results as $row) {
                $value = maybe_unserialize($row->meta_value);
                if (!is_array($value) || empty($value)) {
                    continue;
                }

                if ($this->needs_migration($value)) {
                    $needs_migration++;
                } else {
                    $already_correct++;
                }
            }

            $offset += $chunk_size;
        }

        return [
            'total' => $total,
            'needs_migration' => $needs_migration,
            'already_correct' => $already_correct,
        ];
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

        // Get batch of posts that need migration
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s LIMIT %d",
            self::META_KEY,
            self::BATCH_SIZE * 2 // Get more to account for already-correct ones
        ));

        $migrated = 0;
        $skipped = 0;

        foreach ($results as $row) {
            if ($migrated >= self::BATCH_SIZE) {
                break;
            }

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
            update_post_meta($row->post_id, self::META_KEY, $new_value);
            $migrated++;
        }

        // Check if we're done
        $remaining = $this->get_migration_stats()['needs_migration'];

        if ($remaining === 0) {
            update_option(self::OPTION_KEY, current_time('mysql'));
            add_settings_error(
                'kabelkrant_migration',
                'success',
                sprintf('Migratie voltooid! %d posts gemigreerd in deze batch.', $migrated),
                'success'
            );
        } else {
            add_settings_error(
                'kabelkrant_migration',
                'success',
                sprintf('%d posts gemigreerd. Nog %d te gaan. Klik nogmaals op de knop.', $migrated, $remaining),
                'warning'
            );
        }
    }
}

// Initialize the migration
new KabelkrantDagenMigration();
