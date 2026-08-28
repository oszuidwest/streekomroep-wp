<?php

/**
 * Regiokeuze: visitors pick one of the configured broadcast regions and get
 * region-specific top stories on the front page.
 *
 * The choice is stored server-side in a cookie so the front page can be
 * rendered without client-side logic. NOTE: with full-page caching in front
 * of the site (reverse proxy/CDN) the cache key for the front page MUST
 * include the value of this cookie, otherwise visitors get each other's
 * region variant.
 */

const ZW_REGION_COOKIE = 'zw_regio';

/**
 * Reserved ?regio= value (and cookie state) meaning "no region preference".
 */
const ZW_REGION_ALL = 'alle';

/**
 * Resolve the region term ids configured on a desking block to Timber terms.
 *
 * @param mixed $term_ids ACF taxonomy field value (array of term ids).
 * @return \Timber\Term[]
 */
function zw_get_region_choices($term_ids): array
{
    $terms = [];
    foreach (array_filter((array) $term_ids) as $term_id) {
        $term = \Timber\Timber::get_term((int) $term_id);
        if ($term && 'regio' === $term->taxonomy) {
            $terms[] = $term;
        }
    }

    return $terms;
}

/**
 * Return the visitor's chosen region, but only when it is one of the
 * configured choices. Returns null when no (valid) choice was made, which
 * means: show the general top stories.
 *
 * @param \Timber\Term[] $regions
 */
function zw_get_selected_region(array $regions): ?\Timber\Term
{
    if (!isset($_COOKIE[ZW_REGION_COOKIE])) {
        return null;
    }

    $slug = sanitize_title(wp_unslash($_COOKIE[ZW_REGION_COOKIE]));
    foreach ($regions as $region) {
        if ($region->slug === $slug) {
            return $region;
        }
    }

    return null;
}

/**
 * Handle ?regio=<slug> on the front page: persist the choice in a cookie and
 * redirect back to a clean URL so the parameter never ends up in cached or
 * shared links. ?regio=alle clears the preference.
 */
function zw_handle_region_switch(): void
{
    if (!is_front_page() || !isset($_GET['regio'])) {
        return;
    }

    $slug = sanitize_title(wp_unslash($_GET['regio']));
    $secure = is_ssl();
    $options = [
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ];

    if ('' === $slug || ZW_REGION_ALL === $slug || !get_term_by('slug', $slug, 'regio')) {
        setcookie(ZW_REGION_COOKIE, '', ['expires' => time() - HOUR_IN_SECONDS] + $options);
    } else {
        setcookie(ZW_REGION_COOKIE, $slug, ['expires' => time() + YEAR_IN_SECONDS] + $options);
    }

    nocache_headers();
    wp_safe_redirect(home_url('/'));
    exit;
}

add_action('template_redirect', 'zw_handle_region_switch');
