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
Timber::render(array('page-' . $timber_post->post_name . '.twig', 'page.twig'), $context);
