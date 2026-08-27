<?php

use Timber\Integration\CoAuthorsPlus\CoAuthorsPlusUser;
use Timber\Timber;

$context = Timber::context();

// Guest-author archives lack an author query var, so Timber otherwise resolves the current user.
$queried_author = get_queried_object();
if (($queried_author->type ?? null) === 'guest-author') {
    $author = CoAuthorsPlusUser::from_guest_author($queried_author);
} else {
    $author = $queried_author instanceof WP_User ? Timber::get_user($queried_author) : null;
}

$context['author'] = $author;
if ($author) {
    $context['title'] = 'Artikelen geschreven door ' . $author->name();
}

Timber::render(['author.twig', 'archive.twig'], $context);
