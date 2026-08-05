<?php
/**
 * Template Name: Colofon
 */

$context = Timber::context();

$timber_post = Timber::get_post();
$context['post'] = $timber_post;

$people = [];
$user_ids = get_field('colofon_users', $timber_post->ID);
foreach (is_array($user_ids) ? $user_ids : [] as $user_id) {
    $user = get_userdata($user_id);
    if (!$user) {
        continue;
    }

    $photo_id = get_field('gebruiker_profielfoto', 'user_' . $user_id);
    $photo = $photo_id ? wp_get_attachment_url($photo_id) : null;

    $name_parts = preg_split('/\s+/', trim($user->display_name)) ?: [];
    $initials = mb_substr($name_parts[0] ?? '', 0, 1);
    if (count($name_parts) > 1) {
        $initials .= mb_substr(end($name_parts), 0, 1);
    }

    $people[] = [
        'name' => $user->display_name,
        // Yoast SEO Premium stores the profile field "Functienaam" in this user meta;
        // the same value feeds the jobTitle in Yoast's Person schema.
        'role' => trim((string)get_user_meta($user_id, 'job_title', true)),
        'photo' => $photo ?: null,
        'email' => $user->user_email,
        'author_url' => count_user_posts($user_id, 'post', true) > 0 ? get_author_posts_url($user_id) : null,
        'initials' => mb_strtoupper($initials),
    ];
}
$context['people'] = $people;

Timber::render(['page-colofon.twig', 'page.twig'], $context);
