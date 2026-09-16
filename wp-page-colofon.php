<?php
/**
 * Template Name: Colofon
 */

$context = Timber::context();

$timber_post = Timber::get_post();
$context['post'] = $timber_post;

$people = [];
$user_ids = get_field('colofon_users', $timber_post->ID) ?: [];
if ($user_ids) {
    $users = get_users(['include' => $user_ids, 'orderby' => 'include']);
    $post_counts = count_many_users_posts($user_ids, 'post', true);

    foreach ($users as $user) {
        $user_id = $user->ID;

        // count_many_users_posts() skips the get_usernumposts filter Co-Authors Plus uses to count co-authored posts.
        $has_published_posts = ($post_counts[$user_id] ?? 0) > 0 || count_user_posts($user_id, 'post', true) > 0;

        $photo_id = get_field('gebruiker_profielfoto', 'user_' . $user_id);

        $name_parts = preg_split('/\s+/', trim($user->display_name)) ?: [];
        $initials = mb_substr($name_parts[0] ?? '', 0, 1);
        if (count($name_parts) > 1) {
            $initials .= mb_substr(end($name_parts), 0, 1);
        }

        // Yoast stores "Functienaam" as jobTitle in wpseo_user_schema.
        $yoast_schema = get_user_meta($user_id, 'wpseo_user_schema', true);
        $job_title = is_array($yoast_schema) ? trim((string)($yoast_schema['jobTitle'] ?? '')) : '';

        $people[] = [
            'name' => $user->display_name,
            'role' => $job_title,
            'photo' => $photo_id ? wp_get_attachment_url($photo_id) : null,
            'email' => $user->user_email,
            'author_url' => $has_published_posts ? get_author_posts_url($user_id) : null,
            'initials' => mb_strtoupper($initials),
        ];
    }
}
$context['people'] = $people;

Timber::render(['page-colofon.twig', 'page.twig'], $context);
