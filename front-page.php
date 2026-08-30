<?php

use Streekomroep\TelevisionBroadcast;
use Streekomroep\TvGemistCache;

$zwProfile = isset($_GET['zw_profile']) && '1' === sanitize_text_field(wp_unslash($_GET['zw_profile']));
$zwProfileMetrics = [];
$zwProfileLastTime = microtime(true);
$zwProfileLastQueries = get_num_queries();
$zwProfileRequestTime = $zwProfileLastTime;
if (isset($_SERVER['REQUEST_TIME_FLOAT'])) {
    $zwProfileRequestTime = (float) $_SERVER['REQUEST_TIME_FLOAT'];
}

if ($zwProfile) {
    global $zwProfileThemeStart, $zwProfileThemeQueries;
    if (isset($zwProfileThemeStart, $zwProfileThemeQueries)) {
        $zwProfileMetrics['wp_bootstrap'] = [
            'duration' => ($zwProfileThemeStart - $zwProfileRequestTime) * 1000,
            'queries' => $zwProfileThemeQueries,
        ];
        $zwProfileMetrics['theme_bootstrap'] = [
            'duration' => ($zwProfileLastTime - $zwProfileThemeStart) * 1000,
            'queries' => $zwProfileLastQueries - $zwProfileThemeQueries,
        ];
    }
}

$zwProfileMark = static function (string $name) use (
    $zwProfile,
    &$zwProfileMetrics,
    &$zwProfileLastTime,
    &$zwProfileLastQueries
): void {
    if (!$zwProfile) {
        return;
    }

    $now = microtime(true);
    $queries = get_num_queries();
    $zwProfileMetrics[$name] = [
        'duration' => ($now - $zwProfileLastTime) * 1000,
        'queries' => $queries - $zwProfileLastQueries,
    ];
    $zwProfileLastTime = $now;
    $zwProfileLastQueries = $queries;
};

/** Primes front-page attachment caches to avoid per-image database queries. */
function zw_prime_front_page_caches(array $blocks): void
{
    $attachments = [];

    foreach ($blocks as $block) {
        foreach ($block['posts'] ?? [] as $post) {
            $attachments[] = get_post_thumbnail_id($post->ID);
        }

        foreach (array_merge($block['videos'] ?? [], $block['shows'] ?? []) as $item) {
            $attachments[] = get_post_thumbnail_id($item['show']->ID);
        }

        // Use raw term meta to avoid eager ACF image hydration.
        foreach ($block['terms'] ?? [] as $term) {
            $attachments[] = get_term_meta($term->id, 'dossier_afbeelding_hoog', true);
        }

        if (!empty($block['term'])) {
            $attachments[] = get_term_meta($block['term']->id, 'dossier_afbeelding_breed', true);
            $attachments[] = get_term_meta($block['term']->id, 'dossier_afbeelding_hoog', true);
        }
    }

    $attachments = array_unique(array_filter(array_map('intval', $attachments)));
    if ($attachments) {
        _prime_post_caches($attachments, false, true);

        // ACF image formatting resolves attachment parent permalinks.
        $parents = array_unique(array_filter(array_map('wp_get_post_parent_id', $attachments)));
        if ($parents) {
            _prime_post_caches($parents, true, true);
        }
    }
}

$context = Timber::context();
$zwProfileMark('context');

$timber_post = Timber::get_post();
$context['post'] = $timber_post;
$zwProfileMark('post');

$blocks = zw_acf_rows($context['options']['desking_blokken_voorpagina']);
$zwProfileMark('acf_blocks');

foreach ($blocks as &$block) {
    $zwBlockStartTime = microtime(true);
    $zwBlockStartQueries = get_num_queries();
    do_action('qm/start', $block['acf_fc_layout']);
    switch ($block['acf_fc_layout']) {
        case 'blok_top_stories':
            $block['posts'] = Timber::get_posts([
                'post_type' => 'post',
                'post_status' => 'publish',
                'posts_per_page' => 2,
                'no_found_rows' => true,
                'ignore_sticky_posts' => true,
                'tax_query' => [
                    [
                        'taxonomy' => 'ranking',
                        'field'    => 'slug',
                        'terms'    => 'top-story',
                    ]
                ]
            ]);
            break;

        case 'blok_tv_gemist':
            $resolved = TvGemistCache::getBlock(
                (bool) $block['ontdubbel'],
                is_numeric($block['aantal_videos']) ? (int) $block['aantal_videos'] : 4
            );
            $block['videos'] = $resolved['videos'];
            $block['shows'] = $resolved['shows'];
            break;

        case 'blok_artikel_lijst':
            $block['posts'] = Timber::get_posts([
                'post_type' => 'post',
                'post_status' => 'publish',
                'posts_per_page' => $block['aantal_artikelen'],
                'offset' => $block['offset'],
                'no_found_rows' => true,
                'ignore_sticky_posts' => true,
                'tax_query' => [
                    [
                        'taxonomy' => 'ranking',
                        'field'    => 'slug',
                        'terms'    => ['top-story', 'achterkant'],
                        'operator' => 'NOT IN',
                    ]
                ]
            ]);
            break;

        case 'blok_fragmenten_carrousel':
            $block['posts'] = Timber::get_posts([
                'post_type' => 'fragment',
                'post_status' => 'publish',
                'posts_per_page' => 5,
                'no_found_rows' => true,
                'ignore_sticky_posts' => true,
            ]);
            break;

        case 'blok_dossier':
            $dossierTerm = get_term($block['selecteer_dossier'], 'dossier');
            if (!$dossierTerm || is_wp_error($dossierTerm)) {
                $block['acf_fc_layout'] = 'error';
                $block['error'] = 'Er is geen dossier geselecteerd';
                break;
            }
            $overrideTitle = trim((string) ($block['tekst_boven_dossier'] ?? ''));
            $block['dossier_titel'] = $overrideTitle !== '' ? $overrideTitle : $dossierTerm->name;
            $block['term'] = Timber::get_term($block['selecteer_dossier'], 'dossier');
            $block['posts'] = Timber::get_posts(
                [
                    'posts_per_page' => $block['aantal_artikelen'],
                    'offset' => $block['offset'],
                    'post_type' => 'post',
                    'post_status' => 'publish',
                    'no_found_rows' => true,
                    'ignore_sticky_posts' => true,
                    'tax_query' => [
                        [
                            'taxonomy' => 'dossier',
                            'terms' => $block['selecteer_dossier'],
                        ]
                    ]
                ]
            );
            break;

        case 'blok_dossiers_carrousel':
            $terms = Timber::get_terms([
                'taxonomy' => 'dossier',
                'hide_empty' => true,
            ]);

            $minCount = 2;
            $terms = array_filter($terms, function ($term) use ($minCount) {
                return $term->count >= $minCount;
            });

            // Order dossiers without loading their individual posts.
            global $wpdb;
            $rows = $wpdb->get_results(
                'SELECT tt.term_id, MAX(p.post_date) AS latest'
                . ' FROM ' . $wpdb->term_relationships . ' tr'
                . ' INNER JOIN ' . $wpdb->term_taxonomy . ' tt ON tt.term_taxonomy_id = tr.term_taxonomy_id'
                . ' INNER JOIN ' . $wpdb->posts . ' p ON p.ID = tr.object_id'
                . " WHERE tt.taxonomy = 'dossier'"
                . " AND p.post_type = 'post'"
                . " AND p.post_status = 'publish'"
                . ' GROUP BY tt.term_id'
            );

            $latestByTerm = [];
            foreach ($rows as $row) {
                $latestByTerm[(int) $row->term_id] = $row->latest;
            }

            // Include child-term publications when ordering parent dossiers.
            $parents = get_terms([
                'taxonomy' => 'dossier',
                'hide_empty' => false,
                'fields' => 'id=>parent',
            ]);
            $parents = is_wp_error($parents) ? [] : $parents;

            foreach (array_keys($latestByTerm) as $termId) {
                $latest = $latestByTerm[$termId];
                $parent = (int) ($parents[$termId] ?? 0);
                $depth = 0;
                // Cap traversal to protect against corrupt parent cycles.
                while ($parent && $depth++ < 10) {
                    if (strcmp($latest, $latestByTerm[$parent] ?? '') > 0) {
                        $latestByTerm[$parent] = $latest;
                    }
                    $parent = (int) ($parents[$parent] ?? 0);
                }
            }

            usort($terms, function ($lhs, $rhs) use ($latestByTerm) {
                return strcmp($latestByTerm[$rhs->id] ?? '', $latestByTerm[$lhs->id] ?? '');
            });

            $block['terms'] = $terms;
            break;

        case 'blok_nu_op_fmtv':
            $schedule = new \Streekomroep\BroadcastSchedule();
            $block['fm'] = $schedule->getCurrentRadioBroadcast();
            $block['tv'] = array_map(function (TelevisionBroadcast $item) {
                return $item->name;
            }, $schedule->getToday()->television);
            $block['links'] = [
                'fm' => zw_get_page_by_template('wp-page-fm-player.php'),
                'tv' => zw_get_page_by_template('wp-page-tv-player.php')
            ];
            break;
    }
    do_action('qm/stop', $block['acf_fc_layout']);

    if ($zwProfile) {
        $metric = 'block_' . sanitize_key($block['acf_fc_layout']);
        $duration = (microtime(true) - $zwBlockStartTime) * 1000;
        $queries = get_num_queries() - $zwBlockStartQueries;
        $zwProfileMetrics[$metric]['duration'] = ($zwProfileMetrics[$metric]['duration'] ?? 0) + $duration;
        $zwProfileMetrics[$metric]['queries'] = ($zwProfileMetrics[$metric]['queries'] ?? 0) + $queries;
    }
}
unset($block);
$zwProfileMark('blocks');

zw_prime_front_page_caches($blocks);
$zwProfileMark('prime_attachments');

$zwProfileTermImages = [];
if ($zwProfile) {
    foreach ($blocks as $profileBlock) {
        foreach ($profileBlock['terms'] ?? [] as $profileTerm) {
            $rawImage = get_term_meta($profileTerm->id, 'dossier_afbeelding_hoog', true);
            $formattedImage = get_field(
                'dossier_afbeelding_hoog',
                get_term($profileTerm->id, $profileTerm->taxonomy)
            );
            $zwProfileTermImages[] = [
                'term' => $profileTerm->id,
                'raw' => $rawImage,
                'formatted' => is_array($formattedImage) ? ($formattedImage['ID'] ?? null) : $formattedImage,
            ];

            if (count($zwProfileTermImages) === 5) {
                break 2;
            }
        }
    }
}

$context['options']['desking_blokken_voorpagina'] = $blocks;

if (!$zwProfile) {
    Timber::render('front-page.twig', $context);
    return;
}

$html = Timber::compile('front-page.twig', $context);
$zwProfileMark('twig');

$serverTiming = [];
$queryProfile = [];
foreach ($zwProfileMetrics as $name => $metric) {
    $serverTiming[] = sprintf('%s;dur=%.2f', $name, $metric['duration']);
    $queryProfile[] = $name . '=' . $metric['queries'];
}

header('Server-Timing: ' . implode(', ', $serverTiming));
header('X-ZW-Queries: ' . get_num_queries());
header('X-ZW-Query-Profile: ' . implode(', ', $queryProfile));
header('X-ZW-Term-Images: ' . wp_json_encode($zwProfileTermImages));
header('Cache-Control: no-store');

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Timber rendered the complete response.
echo $html;

global $wpdb;
if (!empty($wpdb->queries)) {
    $queryDump = [];
    foreach ($wpdb->queries as $index => $query) {
        $queryDump[] = [
            'number' => $index + 1,
            'duration' => $query[1],
            'sql' => $query[0],
            'caller' => $query[2] ?? '',
        ];
    }

    echo '<pre id="zw-query-dump" style="white-space:pre-wrap;background:#fff;color:#000;padding:1rem">';
    echo esc_html(wp_json_encode($queryDump, JSON_PRETTY_PRINT));
    echo '</pre>';
}
