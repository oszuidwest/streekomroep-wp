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
 * 4. VERWIJDER DIT BESTAND en de require in functions.php
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
        global $wpdb;

        $is_migrated = get_option(self::OPTION_KEY, false);

        // Quick count using SQL LIKE for Dutch abbreviations
        $needs_migration = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->postmeta}
             WHERE meta_key = %s
             AND (meta_value LIKE %s OR meta_value LIKE %s OR meta_value LIKE %s)",
            self::META_KEY,
            '%"ma"%',
            '%"di"%',
            '%"wo"%'
        ));

        $total = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s",
            self::META_KEY
        ));

        ?>
        <div class="wrap">
            <h1>Migratie: Kabelkrant Dagen</h1>

            <div style="background: #fff3cd; border: 1px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 4px;">
                <strong>⚠️ TODO:</strong> Verwijder <code>lib/migration-kabelkrant-dagen.php</code> en de require in
                <code>functions.php</code> zodra de migratie compleet is!
            </div>

            <?php settings_errors('kabelkrant_migration'); ?>

            <h2>Status</h2>
            <table class="widefat" style="max-width: 500px;">
                <tr>
                    <td>Totaal posts met kabelkrant dagen:</td>
                    <td><strong><?php echo esc_html($total); ?></strong></td>
                </tr>
                <tr>
                    <td>Te migreren (oude format):</td>
                    <td><strong style="color: <?php echo $needs_migration > 0 ? '#dc3545' : '#28a745'; ?>">
                        <?php echo esc_html($needs_migration); ?>
                    </strong></td>
                </tr>
            </table>

            <?php if ($needs_migration > 0): ?>
                <div style="background: #e7f3ff; border: 1px solid #0073aa; padding: 15px; margin: 20px 0; border-radius: 4px;">
                    <strong>ℹ️ Info:</strong> De migratie gebruikt directe SQL REPLACE queries en is snel.
                </div>

                <form method="post" style="margin-top: 20px;">
                    <?php wp_nonce_field('kabelkrant_dagen_migration', 'migration_nonce'); ?>
                    <input type="hidden" name="action" value="run_migration">
                    <button type="submit" class="button button-primary button-hero">
                        🚀 Start Migratie
                    </button>
                </form>
            <?php elseif ($is_migrated): ?>
                <div style="background: #d4edda; border: 1px solid #28a745; padding: 15px; margin: 20px 0; border-radius: 4px;">
                    <strong>✅ Migratie voltooid op <?php echo esc_html($is_migrated); ?></strong>
                </div>
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

            <hr style="margin: 30px 0;">

            <form method="post">
                <?php wp_nonce_field('kabelkrant_dagen_migration', 'migration_nonce'); ?>
                <input type="hidden" name="action" value="reset_migration">
                <button type="submit" class="button" onclick="return confirm('Weet je zeker dat je de migratie status wilt resetten?');">
                    Reset Migratie Status
                </button>
            </form>
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
                add_settings_error('kabelkrant_migration', 'reset', 'Migratie status gereset.', 'info');
                break;
        }
    }

    private function run_migration(): void
    {
        global $wpdb;

        // Direct SQL replacements - much faster than PHP loops
        // Replace Dutch day abbreviations with numeric values in serialized arrays
        $replacements = [
            // Full replacements for serialized strings
            // Format: s:2:"ma" -> s:1:"1", s:2:"di" -> s:1:"2", etc.
            's:2:"ma"' => 's:1:"1"',
            's:2:"di"' => 's:1:"2"',
            's:2:"wo"' => 's:1:"3"',
            's:2:"do"' => 's:1:"4"',
            's:2:"vr"' => 's:1:"5"',
            's:4:"vrij"' => 's:1:"5"',
            's:2:"za"' => 's:1:"6"',
            's:2:"zo"' => 's:1:"7"',
        ];

        $updated = 0;

        foreach ($replacements as $old => $new) {
            $result = $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->postmeta}
                 SET meta_value = REPLACE(meta_value, %s, %s)
                 WHERE meta_key = %s
                 AND meta_value LIKE %s",
                $old,
                $new,
                self::META_KEY,
                '%' . $wpdb->esc_like($old) . '%'
            ));

            if ($result !== false) {
                $updated += $result;
            }
        }

        // Clear any caches
        wp_cache_flush();

        // Check remaining
        $remaining = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->postmeta}
             WHERE meta_key = %s
             AND (meta_value LIKE %s OR meta_value LIKE %s OR meta_value LIKE %s)",
            self::META_KEY,
            '%"ma"%',
            '%"di"%',
            '%"wo"%'
        ));

        if ($remaining === 0) {
            update_option(self::OPTION_KEY, current_time('mysql'));
            add_settings_error(
                'kabelkrant_migration',
                'success',
                sprintf('Migratie voltooid! %d database rijen aangepast.', $updated),
                'success'
            );
        } else {
            add_settings_error(
                'kabelkrant_migration',
                'partial',
                sprintf('%d rijen aangepast, maar nog %d te gaan. Klik nogmaals.', $updated, $remaining),
                'warning'
            );
        }
    }
}

// Initialize the migration
new KabelkrantDagenMigration();
