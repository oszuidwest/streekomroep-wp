<?php
$context = Timber::context();

$timber_post = new Timber\Post();
$context['post'] = $timber_post;
$context['options'] = get_fields('options');
foreach ($context['options']['blokken_sidebar'] as &$block) {
    switch ($block['acf_fc_layout']) {
        case 'blok_meest_gelezen':
            $block['posts'] = Timber::get_posts(['tag' => 'populair']);
            break;
    }
}

foreach ($context['options']['blokken_voorpagina'] as &$block) {
    switch ($block['acf_fc_layout']) {
        case 'blok_artikelen_lijst':
            $block['posts'] = Timber::get_posts([
                'posts_per_page' => $block['artikel_lijst_aantal_artikelen'],
                'offset' => $block['artikel_lijst_offset'],
                'ignore_sticky_posts' => true,
            ]);
            break;
    }
}

$args = [
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
];
$context['topstories'] = Timber::get_posts($args);

Timber::render(array('page-' . $timber_post->post_name . '.twig', 'page.twig'), $context);
