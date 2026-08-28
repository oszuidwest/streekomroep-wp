<?php

use Streekomroep\GuestAuthor;
use Timber\Timber;

$context = Timber::context();

// Guest-author archives lack an author query var, so Timber otherwise resolves the current user.
$queried_author = get_queried_object();
if (($queried_author->type ?? null) === 'guest-author') {
    $context['author'] = GuestAuthor::from_guest_author($queried_author);
} else {
    $context['author'] = $queried_author instanceof WP_User ? Timber::get_user($queried_author) : null;
}

Timber::render(['author.twig', 'archive.twig'], $context);
