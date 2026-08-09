<?php
/**
 * The template for displaying Archive pages.
 *
 * Used to display archive-type pages if nothing more specific matches a query.
 * For example, puts together date-based pages if no date.php file exists.
 *
 * Learn more: http://codex.wordpress.org/Template_Hierarchy
 *
 * Methods for TimberHelper can be found in the /lib sub-directory
 *
 * @package  WordPress
 * @subpackage  Timber
 * @since   Timber 0.2
 */

$templates = [ 'archive.twig', 'index.twig' ];

$context = Timber::context();

$context['title'] = 'Archief';
if (is_day()) {
    $context['title'] = 'Archief: ' . get_the_date('D M Y');
} elseif (is_month()) {
    $context['title'] = 'Archief: ' . get_the_date('M Y');
} elseif (is_year()) {
    $context['title'] = 'Archief: ' . get_the_date('Y');
} elseif (is_tag()) {
    $context['title'] = single_tag_title('', false);
} elseif (is_category()) {
    $context['title'] = single_cat_title('', false);
    array_unshift($templates, 'archive-' . get_query_var('cat') . '.twig');
} elseif (is_post_type_archive()) {
    $context['title'] = post_type_archive_title('', false);
    array_unshift($templates, 'archive-' . get_post_type() . '.twig');
} elseif (is_tax('dossier')) {
    $context['term'] = Timber::get_term(get_queried_object());
    array_unshift($templates, 'dossier.twig');
}

$context['posts'] = Timber::get_posts();

if (is_post_type_archive() && get_post_type() === 'tv') {
    $context['posts'] = $context['posts']->to_array();
    foreach ($context['posts'] as $show) {
        $videos = \Streekomroep\VideoCollection::forTvShow($show->id);
        $show->lastBroadcast = isset($videos[0]) ? $videos[0]->getBroadcastDate() : null;
    }

    usort($context['posts'], function ($lhs, $rhs) {
        return $rhs->lastBroadcast <=> $lhs->lastBroadcast;
    });
}

if (is_post_type_archive('fm')) {
    $context['posts'] = $context['posts']->to_array();
    $context['fm_schedules'] = [];
    $sort_keys = [];

    $weekdays = array_values(\Streekomroep\BroadcastDay::WEEKDAY_NAMES);
    if (get_field('radio_week_start', 'option') === 'zondag') {
        array_unshift($weekdays, array_pop($weekdays));
    }
    $weekday_positions = array_flip($weekdays);

    foreach ($context['posts'] as $show) {
        $schedule = zw_fm_schedule_rows($show->meta('fm_show_programmatie'));
        $context['fm_schedules'][$show->id] = $schedule;
        $first_slot = null;

        foreach ($schedule as $entry) {
            foreach ($entry['fm_show_dagen'] as $day) {
                $slot = [$weekday_positions[$day], $entry['fm_show_starttijd']];
                if ($first_slot === null || $slot < $first_slot) {
                    $first_slot = $slot;
                }
            }
        }

        $sort_keys[$show->id] = $first_slot ?? [PHP_INT_MAX, ''];
    }

    usort($context['posts'], function ($lhs, $rhs) use ($sort_keys) {
        $slot_order = $sort_keys[$lhs->id] <=> $sort_keys[$rhs->id];
        if ($slot_order !== 0) {
            return $slot_order;
        }

        $title_order = strnatcasecmp(zw_plain_text($lhs->title()), zw_plain_text($rhs->title()));
        return $title_order !== 0 ? $title_order : $lhs->id <=> $rhs->id;
    });
}

Timber::render($templates, $context);
