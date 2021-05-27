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

            $candidates = [];
            foreach ($shows as $show) {
                $videos = $show->meta('vimeo_data');
                if (!is_array($videos)) continue;

                $videos = zw_sort_videos($videos);
                $lastEpisode = array_shift($videos);
                if ($lastEpisode === null) continue;

                $show->lastEpisode = $lastEpisode;
                $candidates[] = $show;
            }

            usort($candidates, function ($left, $right) {
                return $right->lastEpisode->getBroadcastDate() <=> $left->lastEpisode->getBroadcastDate();
            });

            // Show 2 videos and 4 shows
            $videos = array_slice($candidates, 0, 2);
            $shows = array_slice($candidates, 2, 4);

            $videos = array_map(function ($item) {
                $item->lastEpisode->show = $item;
                return $item->lastEpisode;
            }, $videos);

            $block['videos'] = $videos;
            $block['shows'] = $shows;
            break;

        case 'blok_artikel_lijst':
            $block['posts'] = Timber::get_posts([
                'posts_per_page' => $block['aantal_artikelen'],
                'offset' => $block['offset'],
                'ignore_sticky_posts' => true,
                'meta_query' => [
                    [
                        'key' => 'post_ranking',
                        'value' => '2',
                        'compare' => 'NOT LIKE',
                    ]
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
                    'posts_per_page' => 4,
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
            $block['terms'] = Timber::get_terms([
                'taxonomy' => 'dossier',
                'hide_empty' => true,
            ]);

            foreach ($block['terms'] as $term) {
                $term->post_date = Timber::get_post([
                    'ignore_sticky_posts' => true,
                    'posts_per_page' => 1,

                    'tax_query' => [
                        [
                            'taxonomy' => $term->taxonomy,
                            'terms' => $term->id,
                        ],
                    ],
                ])->post_date;
            }
            usort($block['terms'], function ($lhs, $rhs) {
                return strcmp($rhs->post_date, $lhs->post_date);
            });
            $block['terms'] = array_slice($block['terms'], 0, 5);
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
