<?php
/**
 * The template for displaying Author Archive pages
 *
 * Methods for TimberHelper can be found in the /lib sub-directory
 *
 * @package  WordPress
 * @subpackage  Timber
 * @since    Timber 0.1
 */

use Timber\Integration\CoAuthorsPlus\CoAuthorsPlusUser;
use Timber\Timber;

$context = Timber::context();

// Timber::context() resolves the author from the `author` query var, which is empty
// for Co-Authors Plus guest authors (Timber then falls back to the current user).
// Resolve from the queried object instead and overwrite unconditionally.
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
