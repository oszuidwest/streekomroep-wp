<?php

use Streekomroep\TelevisionBroadcast;
use Streekomroep\VideoCollection;

$context = Timber::context();

$timber_post = Timber::get_post();
$context['post'] = $timber_post;

$blocks = $context['options']['desking_blokken_voorpagina'];
if (!is_array($blocks)) {
    $blocks = [];
}

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
            $deduplicate = (bool) $block['ontdubbel'];
            $videos_to_show = is_numeric($block['aantal_videos']) ? (int) $block['aantal_videos'] : 4;

            // The candidate lists are derived from bunny_data post meta on every
            // TV show, which is expensive to load and sort. The scalar result is
            // cached until the ten-minute Bunny cron refreshes the meta.
            $cacheKey = ($deduplicate ? '1' : '0') . '_' . $videos_to_show;
            $candidates = \Streekomroep\TvGemistCache::get($cacheKey);

            if ($candidates !== null) {
                $showIds = array_unique(array_merge(
                    array_column($candidates['videos'], 'show'),
                    array_column($candidates['shows'], 'show')
                ));

                $showsById = [];
                if ($showIds) {
                    $shows = Timber::get_posts([
                        'post_type' => 'tv',
                        'post_status' => 'publish',
                        'post__in' => $showIds,
                        'ignore_sticky_posts' => true,
                        'nopaging' => true,
                    ]);
                    foreach ($shows as $show) {
                        $showsById[$show->ID] = $show;
                    }
                }

                $resolve = static function ($ref) use ($showsById) {
                    $show = $showsById[$ref['show']] ?? null;
                    if (!$show) {
                        return null;
                    }

                    $video = VideoCollection::findVideo($show->ID, $ref['video']);
                    return $video ? ['show' => $show, 'video' => $video] : null;
                };

                $resolvedVideos = array_values(array_filter(array_map($resolve, $candidates['videos'])));
                $resolvedShows = array_values(array_filter(array_map($resolve, $candidates['shows'])));

                // A ref that no longer resolves (deleted show, vanished video)
                // means the cache is stale; rebuild instead of rendering a
                // shrunken block until the next invalidation.
                if (
                    count($resolvedVideos) === count($candidates['videos'])
                    && count($resolvedShows) === count($candidates['shows'])
                ) {
                    $block['videos'] = $resolvedVideos;
                    $block['shows'] = $resolvedShows;
                    break;
                }
            }

            $shows = Timber::get_posts([
                'post_type' => 'tv',
                'post_status' => 'publish',
                'ignore_sticky_posts' => true,
                'nopaging' => true,
            ]);

            $episodes_with_duplicate_shows = [];
            $latest_episode_per_show = [];

            $candidate = static fn ($show, $video) => ['show' => $show, 'video' => $video];
            foreach ($shows as $show) {
                $videos = VideoCollection::forTvShow($show->ID);
                if (!$videos) {
                    continue;
                }

                // Keep one latest episode per show for deduplicated videos and the secondary show list.
                $latest_episode_per_show[] = $candidate($show, $videos[0]);

                // Without deduplication, every recent episode may become a featured video.
                if (false === $deduplicate) {
                    $videos = array_slice($videos, 0, 10); // limit the buildup of the array.
                    foreach ($videos as $video) {
                        $episodes_with_duplicate_shows[] = $candidate($show, $video);
                    }
                }
            }

            // Rank shows by the broadcast date of their latest episode.
            $newestFirst = static fn ($left, $right) => $right['video']->getBroadcastDate() <=> $left['video']->getBroadcastDate();
            usort($latest_episode_per_show, $newestFirst);

            if (!empty($episodes_with_duplicate_shows)) {
                // Rank the expanded episode pool independently when duplicate shows are allowed.
                usort($episodes_with_duplicate_shows, $newestFirst);
            }

            $block['videos'] = array_slice($episodes_with_duplicate_shows ?: $latest_episode_per_show, 0, $videos_to_show);
            $featured_show_ids = array_map(static fn ($item) => $item['show']->ID, $block['videos']);
            $block['shows'] = array_slice(array_values(array_filter($latest_episode_per_show, static fn ($item) => !in_array($item['show']->ID, $featured_show_ids, true))), 0, 4);

            $toRef = static fn ($item) => ['show' => $item['show']->ID, 'video' => $item['video']->getId()];
            \Streekomroep\TvGemistCache::set($cacheKey, [
                'videos' => array_map($toRef, $block['videos']),
                'shows' => array_map($toRef, $block['shows']),
            ]);
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
