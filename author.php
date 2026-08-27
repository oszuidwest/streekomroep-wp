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
$context['posts'] = Timber::get_posts();

$queried_author = get_queried_object();
if ($queried_author instanceof WP_User) {
    $author = Timber::get_user($queried_author);
} elseif (
    $queried_author instanceof stdClass
    && isset($queried_author->type)
    && $queried_author->type === 'guest-author'
) {
    $author = CoAuthorsPlusUser::from_guest_author($queried_author);
} else {
    $author_id = get_query_var('author');
    $author = $author_id ? Timber::get_user((int) $author_id) : null;
}

if ($author) {
    $context['author'] = $author;
    $context['title'] = 'Artikelen geschreven door ' . $author->name();
}

Timber::render($author ? ['author.twig', 'archive.twig'] : ['archive.twig'], $context);
