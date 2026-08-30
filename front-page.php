<?php

use Streekomroep\TelevisionBroadcast;
use Streekomroep\TvGemistCache;

/**
 * Primes attachment caches for the images the front-page blocks will render.
 *
 * Twig hydrates featured images and dossier images one attachment at a time,
 * costing a post and a meta query each. Collecting the attachment IDs up front
 * loads them in two queries total.
 */
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

        // Raw term meta holds the attachment ID; the formatted ACF value would
        // hydrate the attachment right here, one query at a time.
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
    }
}

$context = Timber::context();

$timber_post = Timber::get_post();
$context['post'] = $timber_post;

$blocks = zw_acf_rows($context['options']['desking_blokken_voorpagina']);

foreach ($blocks as &$block) {
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
                // Term does not exist
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

            // Filter out terms with less than $count items
            $minCount = 2;
            $terms = array_filter($terms, function ($term) use ($minCount) {
                return $term->count >= $minCount;
            });

            // The carousel only needs the terms ordered by their most recent
            // publication; one grouped query replaces a post query per dossier.
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

            // The taxonomy is hierarchical and the replaced per-term query
            // matched child terms too, so roll each date up to its ancestors.
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
                // The depth cap only guards against a corrupted parent cycle.
                while ($parent && $depth++ < 10) {
                    if (strcmp($latest, $latestByTerm[$parent] ?? '') > 0) {
                        $latestByTerm[$parent] = $latest;
                    }
                    $parent = (int) ($parents[$parent] ?? 0);
                }
            }

            // Sort on most recent post
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
}
unset($block);

zw_prime_front_page_caches($blocks);

$context['options']['desking_blokken_voorpagina'] = $blocks;

Timber::render('front-page.twig', $context);
