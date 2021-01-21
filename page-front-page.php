<?php
$context = Timber::context();

$timber_post = new Timber\Post();
$context['post'] = $timber_post;
$context['options'] = get_fields('options');

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
                'posts_per_page' => 3,
                'ignore_sticky_posts' => true,
            ]);
            break;

        case 'blok_dossiers_carrousel':
            $block['terms'] = Timber::get_terms([
                'taxonomy' => 'dossier'
            ]);
            break;
    }
}


Timber::render(array('page-' . $timber_post->post_name . '.twig', 'page.twig'), $context);
