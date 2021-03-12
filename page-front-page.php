<?php
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
            $vimeo = get_transient('vimeo/videos');
            if ($vimeo === false) {
                $vimeo = [];
                try {
                    $response = vimeo_get('/me/videos?sort=date');
                    $response = json_decode($response['body']);
                    $vimeo = $response->data;
                    set_transient('vimeo/videos', $vimeo, 1 * HOUR_IN_SECONDS);
                } catch (Throwable $t) {
                    ob_start();
                    var_dump($t);
                    $obj = ob_get_clean();
                    trigger_error('Error fetching vimeo : ' . $obj, E_USER_NOTICE);
                }
            }

            $vimeo = array_map(function ($a) {
                return new \Streekomroep\SafeObject($a, 'video');
            }, $vimeo);

            $shows = \Timber\Timber::get_posts([
                'post_type' => 'tv',
                'posts_per_page' => 4,
                'ignore_sticky_posts' => true,
            ]);
            $block['shows'] = $shows;

            $videos = [];
            foreach ($vimeo as $video) {
                if ($video->parent_folder === null) continue;

                $projectId = basename($video->parent_folder->uri);
                $args = [
                    'post_type' => 'tv',
                    'meta_key' => 'tv_show_gemist_locatie',
                    'meta_value' => $projectId
                ];
                $shows = Timber::get_posts($args);
                if (count($shows) > 0) {
                    $video->show = $shows[0];
                    $videos[] = $video;
                }

                if (count($videos) == 2) {
                    break;
                }
            }
            $block['videos'] = $videos;
            break;

        case 'blok_artikel_lijst':
            $block['posts'] = Timber::get_posts([
                'posts_per_page' => $block['aantal_artikelen'],
                'offset' => $block['offset'],
                'ignore_sticky_posts' => true,
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
                'taxonomy' => 'dossier'
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
            break;
    }
}


Timber::render(array('page-' . $timber_post->post_name . '.twig', 'page.twig'), $context);
