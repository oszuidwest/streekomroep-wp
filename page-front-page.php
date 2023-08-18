<?php

use Streekomroep\TelevisionBroadcast;
use Streekomroep\Video;

$context = Timber::context();

$timber_post = new Timber\Post();
$context['post'] = $timber_post;

if ($context['options']['desking_blokken_voorpagina'] === false) {
    $context['options']['desking_blokken_voorpagina'] = [];
}

foreach ($context['options']['desking_blokken_voorpagina'] as &$block) {
    switch ($block['acf_fc_layout']) {
        case 'blok_top_stories':
            $block['posts'] = Timber::get_posts([
                'post_type' => 'post',
                'post_status' => 'publish',
                'posts_per_page' => 3,
                'ignore_sticky_posts' => true,
                'meta_query' => [
                    [
                        'key' => 'post_ranking',
                        'value' => '2',
                        'compare' => 'LIKE',
                    ]
                ]
            ]);
            break;

        case 'blok_tv_gemist':

            $shows = Timber::get_posts([
                'post_type' => 'tv',
                'ignore_sticky_posts' => true,
                'nopaging' => true,
            ]);

            $episodes_with_duplicate_shows = [];
            $latest_episode_per_show = [];

            $deduplicate = $block['ontdubbel'] ? true : false;
            $videos_to_show = $block['aantal_videos'];
            foreach ($shows as $show) {
                $videos = $show->meta(ZW_TV_META_VIDEOS);
                if (!is_array($videos)) continue;

                $videos_for_last_episode = $videos = zw_sort_videos($videos);
                $lastEpisode = array_shift($videos_for_last_episode);
                if ($lastEpisode === null) continue;

                $show->lastEpisode = $lastEpisode;

                /**
                 * Build a list of candidate shows.
                 * We will use this list for the other shows and maybe for the featured shows when deduplication is enabled.
                 * Each array item holds the show it belongs to and the last episode.
                 */
                $latest_episode_per_show[] = [
                    'show' => $show,
                    'video' => $show->lastEpisode,
                ];

                /**
                 * When deduplication is disabled, we will build a second array of candidates.
                 * We loop through all the videos and add them to the array of featured candidates.
                 * Each items holds the show it belongs to and the episode.
                 */
                if (false === $deduplicate) {
                    $videos = array_slice($videos, 0, 10); // limit the buildup of the array.
                    foreach ($videos as $video) {
                        $episodes_with_duplicate_shows[] = [
                            'show' => $show,
                            'video' => $video,
                        ];
                    }
                }
            }

            /**
             * We sort the candidates by broadcast date. 
             * The most recently broadcasted show is the first item in the array.
             */
            usort($latest_episode_per_show, function ($left, $right) {
                return $right['video']->getBroadcastDate() <=> $left['video']->getBroadcastDate();
            });

            // Show 4 videos and 4 shows
            $videos = array_slice($latest_episode_per_show, 0, $videos_to_show);
            $shows = array_slice($latest_episode_per_show, $videos_to_show, 4);

            if (!empty($episodes_with_duplicate_shows)) {

                /**
                 * We sort the videos by broadcast date. 
                 * The most recently broadcasted show is the first item in the array.
                 */
                usort($episodes_with_duplicate_shows, function ($left, $right) {
                    return $right['video']->getBroadcastDate() <=> $left['video']->getBroadcastDate();
                });

                $videos = array_slice($episodes_with_duplicate_shows, 0, $videos_to_show);
            }

            $block['videos'] = $videos;
            $block['shows'] = $shows;
            break;

        case 'blok_artikel_lijst':
            $block['posts'] = Timber::get_posts([
                'posts_per_page' => $block['aantal_artikelen'],
                'offset' => $block['offset'],
                'ignore_sticky_posts' => true,
                'meta_query' => [
                    'relation' => 'AND',
                    [
                        'key' => 'post_ranking',
                        'value' => '2',
                        'compare' => 'NOT LIKE',
                    ],
                    [
                        'key' => 'post_ranking',
                        'value' => '6',
                        'compare' => 'NOT LIKE',
                    ],
                ]
            ]);
            break;

        case 'blok_fragmenten_carrousel':
            $block['posts'] = Timber::get_posts([
                'post_type' => 'fragment',
                'posts_per_page' => 5,
                'ignore_sticky_posts' => true,
            ]);
            break;

        case 'blok_dossier':
            $term = get_term($block['selecteer_dossier'], 'dossier');
            if (!$term || is_wp_error($term)) {
                // Term does not exist
                $block['acf_fc_layout'] = 'error';
                $block['error'] = 'Er is geen dossier geselecteerd';
                break;
            }
            $block['term'] = Timber::get_term($block['selecteer_dossier'], 'dossier');
            $block['posts'] = Timber::get_posts(
                [
                    'posts_per_page' => $block['aantal_artikelen'],
                    'offset' => $block['offset'],
                    'post_type' => 'post',
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
            // Block requires jquery for scrolling
            wp_enqueue_script('jquery');
            $block['terms'] = Timber::get_terms([
                'taxonomy' => 'dossier',
                'hide_empty' => true,
            ]);

            $minCount = 2;
            foreach ($block['terms'] as $term) {
                $term->posts = Timber::get_posts([
                    'ignore_sticky_posts' => true,
                    'posts_per_page' => $minCount,

                    'tax_query' => [
                        [
                            'taxonomy' => $term->taxonomy,
                            'terms' => $term->id,
                        ],
                    ],
                ]);
            }

            // Filter out terms with less than $count items
            $block['terms'] = array_filter($block['terms'], function ($term) use ($minCount) {
                return count($term->posts) == $minCount;
            });

            // Sort on most recent post
            usort($block['terms'], function ($lhs, $rhs) {
                return strcmp($rhs->posts[0]->post_date, $lhs->posts[0]->post_date);
            });
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
}


Timber::render(array('page-' . $timber_post->post_name . '.twig', 'page.twig'), $context);
