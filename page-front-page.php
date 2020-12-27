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

$args = [
    'post_type' => 'post',
    'post_status' => 'publish',
    'posts_per_page' => 3,
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
