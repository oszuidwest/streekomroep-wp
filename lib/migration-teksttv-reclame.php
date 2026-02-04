<?php

/**
 * =============================================================================
 * TODO: VERWIJDER DIT BESTAND NA MIGRATIE!
 * =============================================================================
 *
 * Dit script migreert de oude kabelkrant reclame (v1.9.x) naar het nieuwe
 * Tekst TV reclame formaat (v1.10+).
 *
 * Oude structuur (tv-instellingen):
 * - tv_reclame_slides (repeater)
 *   - tv_reclame_afbeelding (image)
 *   - tv_reclame_start (date: d/m/Y)
 *   - tv_reclame_eind (date: d/m/Y)
 *
 * Nieuwe structuur (teksttv_tv1, teksttv_tv2, etc.):
 * - teksttv_reclame (repeater)
 *   - campagne_slides (gallery)
 *   - campagne_datum_in (date: Y-m-d)
 *   - campagne_datum_uit (date: Y-m-d)
 *   - campagne_seconden (number)
 *   - campagne_groep (checkbox)
 *
 * HOE TE GEBRUIKEN:
 * 1. Ga naar: Tekst TV → Reclame Migratie
 * 2. Bekijk de preview
 * 3. Kies het doelkanaal
 * 4. Klik op "Start Migratie"
 * 5. VERWIJDER DIT BESTAND na migratie
 *
 * =============================================================================
 */

namespace Streekomroep\Migration;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class TekstTVReclameMigration
{
    private const OPTION_KEY = 'streekomroep_teksttv_reclame_migrated';

    public function __construct()
    {
        add_action('admin_menu', [$this, 'add_admin_page'], 99);
        add_action('admin_init', [$this, 'handle_migration']);
    }

    public function add_admin_page(): void
    {
        add_management_page(
            'Migratie: Tekst TV Reclame',
            'Tekst TV Reclame Migratie',
            'manage_options',
            'migrate-teksttv-reclame',
            [$this, 'render_admin_page']
        );
    }

    public function render_admin_page(): void
    {
        $is_migrated = get_option(self::OPTION_KEY, false);
        $old_data = $this->get_old_reclame_data();
        $channels = defined('ZW_TEKSTTV_CHANNELS') ? ZW_TEKSTTV_CHANNELS : [];

        ?>
        <div class="wrap">
            <h1>Migratie: Tekst TV Reclame</h1>

            <div style="background: #fff3cd; border: 1px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 4px;">
                <strong>⚠️ TODO:</strong> Verwijder <code>lib/migration-teksttv-reclame.php</code> en de require in
                <code>functions.php</code> zodra de migratie compleet is!
            </div>

        <?php settings_errors('teksttv_reclame_migration'); ?>

        <?php if ($is_migrated) : ?>
                <div style="background: #d4edda; border: 1px solid #28a745; padding: 15px; margin: 20px 0; border-radius: 4px;">
                    <strong>✅ Migratie voltooid op <?php echo esc_html($is_migrated); ?></strong>
                </div>
        <?php endif; ?>

            <h2>Oude reclame data (tv-instellingen)</h2>

        <?php if (isset($_GET['debug'])) : ?>
                <h3>Debug: Ruwe data</h3>
                <pre style="background: #f5f5f5; padding: 10px; max-height: 400px; overflow: auto;"><?php
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
                print_r($old_data);
                ?></pre>
        <?php endif; ?>

        <?php if (empty($old_data)) : ?>
                <p>Geen oude reclame data gevonden in <code>tv-instellingen</code>.</p>
        <?php else : ?>
                <p>Gevonden: <strong><?php echo count($old_data); ?></strong> oude reclame slides</p>

                <table class="widefat" style="max-width: 800px;">
                    <thead>
                        <tr>
                            <th>Afbeelding</th>
                            <th>Begindatum</th>
                            <th>Einddatum</th>
                            <th>→ Nieuw formaat</th>
                        </tr>
                    </thead>
                    <tbody>
            <?php foreach ($old_data as $index => $slide) : ?>
                            <tr>
                                <td>
                <?php if (!empty($slide['tv_reclame_afbeelding']['url'])) : ?>
                                        <img src="<?php echo esc_url($slide['tv_reclame_afbeelding']['sizes']['thumbnail'] ?? $slide['tv_reclame_afbeelding']['url']); ?>"
                                             style="max-width: 100px; max-height: 60px;">
                <?php else : ?>
                                        <em>Geen afbeelding</em>
                <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($slide['tv_reclame_start'] ?? '-'); ?></td>
                                <td><?php echo esc_html($slide['tv_reclame_eind'] ?? '-'); ?></td>
                                <td>
                <?php
                $converted = $this->convert_slide($slide);
                echo esc_html($converted['campagne_datum_in'] ?? '-');
                echo ' → ';
                echo esc_html($converted['campagne_datum_uit'] ?? '-');
                ?>
                                </td>
                            </tr>
            <?php endforeach; ?>
                    </tbody>
                </table>

            <?php if (!empty($channels)) : ?>
                    <h2>Migreren naar kanaal</h2>
                    <form method="post" style="margin-top: 20px;">
                <?php wp_nonce_field('teksttv_reclame_migration', 'migration_nonce'); ?>
                        <input type="hidden" name="action" value="run_migration">

                        <p>
                            <label for="target_channel"><strong>Doelkanaal:</strong></label><br>
                            <select name="target_channel" id="target_channel" style="min-width: 200px;">
                <?php foreach ($channels as $slug => $name) : ?>
                                    <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($name); ?></option>
                <?php endforeach; ?>
                            </select>
                        </p>

                        <p>
                            <label>
                                <input type="checkbox" name="keep_old" value="1" checked>
                                Behoud oude data (aangeraden voor testen)
                            </label>
                        </p>

                        <button type="submit" class="button button-primary button-hero">
                            🚀 Start Migratie
                        </button>
                    </form>
            <?php else : ?>
                    <p style="color: red;"><strong>Fout:</strong> Geen kanalen geconfigureerd (ZW_TEKSTTV_CHANNELS).</p>
            <?php endif; ?>
        <?php endif; ?>

            <hr style="margin: 30px 0;">

            <form method="post">
        <?php wp_nonce_field('teksttv_reclame_migration', 'migration_nonce'); ?>
                <input type="hidden" name="action" value="reset_migration">
                <button type="submit" class="button" onclick="return confirm('Weet je zeker dat je de migratie status wilt resetten?');">
                    Reset Migratie Status
                </button>
            </form>
        </div>
        <?php
    }

    private function get_old_reclame_data(): array
    {
        // Old ACF field definitions no longer exist, so we need to read raw data from options table
        // ACF stores repeater data as:
        // - options_tv_reclame_slides = count (integer)
        // - options_tv_reclame_slides_0_tv_reclame_afbeelding = image ID
        // - options_tv_reclame_slides_0_tv_reclame_start = date string
        // - options_tv_reclame_slides_0_tv_reclame_eind = date string

        $count = get_option('options_tv_reclame_slides');

        if (!$count || !is_numeric($count)) {
            return [];
        }

        $slides = [];
        for ($i = 0; $i < (int) $count; $i++) {
            $prefix = 'options_tv_reclame_slides_' . $i . '_';

            // Get image ID and convert to array format
            $image_id = get_option($prefix . 'tv_reclame_afbeelding');
            $image_data = null;
            if ($image_id) {
                $image_data = [
                    'ID' => (int) $image_id,
                    'url' => wp_get_attachment_url((int) $image_id),
                    'sizes' => [
                        'thumbnail' => wp_get_attachment_image_url((int) $image_id, 'thumbnail'),
                    ],
                ];
            }

            $slides[] = [
                'tv_reclame_afbeelding' => $image_data,
                'tv_reclame_start' => get_option($prefix . 'tv_reclame_start') ?: '',
                'tv_reclame_eind' => get_option($prefix . 'tv_reclame_eind') ?: '',
            ];
        }

        return $slides;
    }

    private function convert_slide(array $slide): array
    {
        // Convert date format from d/m/Y to Y-m-d
        $start_date = $this->convert_date($slide['tv_reclame_start'] ?? '');
        $end_date = $this->convert_date($slide['tv_reclame_eind'] ?? '');

        // Build gallery array from single image
        $gallery = [];
        if (!empty($slide['tv_reclame_afbeelding']['ID'])) {
            $gallery[] = $slide['tv_reclame_afbeelding']['ID'];
        }

        return [
            'campagne_slides' => $gallery,
            'campagne_datum_in' => $start_date,
            'campagne_datum_uit' => $end_date,
            'campagne_seconden' => 10, // Default 10 seconds
            'campagne_groep' => ['1'], // Default to group 1
        ];
    }

    private function convert_date(string $date): string
    {
        if (empty($date)) {
            return '';
        }

        // Try d/m/Y format first
        $parsed = \DateTime::createFromFormat('d/m/Y', $date);
        if ($parsed) {
            return $parsed->format('Y-m-d');
        }

        // Already in Y-m-d format?
        $parsed = \DateTime::createFromFormat('Y-m-d', $date);
        if ($parsed) {
            return $date;
        }

        return '';
    }

    public function handle_migration(): void
    {
        if (!isset($_POST['migration_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['migration_nonce'])), 'teksttv_reclame_migration')) {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        $action = isset($_POST['action']) ? sanitize_text_field(wp_unslash($_POST['action'])) : '';

        switch ($action) {
            case 'run_migration':
                $this->run_migration();
                break;
            case 'reset_migration':
                delete_option(self::OPTION_KEY);
                add_settings_error('teksttv_reclame_migration', 'reset', 'Migratie status gereset.', 'info');
                break;
        }
    }

    private function run_migration(): void
    {
        if (!function_exists('update_field')) {
            add_settings_error(
                'teksttv_reclame_migration',
                'error',
                'ACF is niet beschikbaar.',
                'error'
            );
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_migration()
        $target_channel = isset($_POST['target_channel']) ? sanitize_text_field(wp_unslash($_POST['target_channel'])) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_migration()
        $keep_old = !empty($_POST['keep_old']);

        if (empty($target_channel)) {
            add_settings_error(
                'teksttv_reclame_migration',
                'error',
                'Geen doelkanaal geselecteerd.',
                'error'
            );
            return;
        }

        $old_data = $this->get_old_reclame_data();

        if (empty($old_data)) {
            add_settings_error(
                'teksttv_reclame_migration',
                'error',
                'Geen oude data om te migreren.',
                'error'
            );
            return;
        }

        // Convert all slides to new format
        $new_campaigns = [];
        foreach ($old_data as $slide) {
            $converted = $this->convert_slide($slide);

            // Only add if there's actually an image
            if (!empty($converted['campagne_slides'])) {
                $new_campaigns[] = $converted;
            }
        }

        if (empty($new_campaigns)) {
            add_settings_error(
                'teksttv_reclame_migration',
                'error',
                'Geen geldige slides gevonden om te migreren.',
                'error'
            );
            return;
        }

        // Get existing campaigns in target channel
        $options_id = 'teksttv_' . $target_channel;
        $existing = get_field('teksttv_reclame', $options_id) ?: [];

        // Merge with existing (new campaigns at the end)
        $merged = array_merge($existing, $new_campaigns);

        // Save to new location
        $result = update_field('teksttv_reclame', $merged, $options_id);

        if ($result) {
            // Optionally delete old data
            if (!$keep_old) {
                $count = get_option('options_tv_reclame_slides');
                if ($count && is_numeric($count)) {
                    for ($i = 0; $i < (int) $count; $i++) {
                        $prefix = 'options_tv_reclame_slides_' . $i . '_';
                        delete_option($prefix . 'tv_reclame_afbeelding');
                        delete_option($prefix . 'tv_reclame_start');
                        delete_option($prefix . 'tv_reclame_eind');
                        // Also delete ACF's internal field reference
                        delete_option('_' . $prefix . 'tv_reclame_afbeelding');
                        delete_option('_' . $prefix . 'tv_reclame_start');
                        delete_option('_' . $prefix . 'tv_reclame_eind');
                    }
                }
                delete_option('options_tv_reclame_slides');
                delete_option('_options_tv_reclame_slides');
            }

            update_option(self::OPTION_KEY, current_time('mysql'));

            add_settings_error(
                'teksttv_reclame_migration',
                'success',
                sprintf(
                    'Migratie voltooid! %d campagnes gemigreerd naar %s.%s',
                    count($new_campaigns),
                    $options_id,
                    $keep_old ? ' Oude data behouden.' : ' Oude data verwijderd.'
                ),
                'success'
            );
        } else {
            add_settings_error(
                'teksttv_reclame_migration',
                'error',
                'Fout bij opslaan van nieuwe data.',
                'error'
            );
        }
    }
}

// Initialize the migration
new TekstTVReclameMigration();
