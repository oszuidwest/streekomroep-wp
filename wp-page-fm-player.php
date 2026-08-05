<?php

/**
 * Template Name: FM Player
 *
 * The live radio page: what is on air now, what plays next, what was just played and where to
 * find the station. The now playing card and the recently played list are refreshed in the
 * browser by static/fm-live.js.
 */

use Carbon\Carbon;
use Streekomroep\AeronToolbox;
use Streekomroep\BroadcastSchedule;
use Streekomroep\RadioBroadcast;

/** Shows to list under "Straks". */
$upcomingLimit = 2;

$context = Timber::context();
$context['post'] = Timber::get_post();

$now = Carbon::now(wp_timezone());
$schedule = new BroadcastSchedule();
$current = $schedule->getCurrentRadioBroadcast();

$context['current'] = $current;

// Filler broadcasts carry only a name, so the byline and the progress bar belong to real shows.
$show = $current?->show;
$context['current_show'] = $show;
$context['current_makers'] = $show ? zw_acf_rows($show->meta('fm_show_makers')) : [];

if ($show) {
    $length = max(1, $current->start->diffInSeconds($current->end));
    $elapsed = min($length, max(0, $current->start->diffInSeconds($now)));
    $context['progress'] = (int)round($elapsed / $length * 100);
    $context['remaining'] = (int)ceil(($length - $elapsed) / 60);
}

/** Describes a show relative to today, so the same card reads well tonight and next Saturday. */
$whenLabel = function (Carbon $start) {
    if ($start->isToday()) {
        return 'straks';
    }

    return $start->isTomorrow() ? 'morgen' : $start->locale('nl')->isoFormat('dddd');
};

// Skip the filler: "Straks" is about programmes, and non-stop music is what happens in between.
$upcoming = [];
$broadcast = $current;
while ($broadcast && count($upcoming) < $upcomingLimit) {
    $broadcast = $schedule->getNextRadioBroadcast($broadcast);
    if (!$broadcast instanceof RadioBroadcast || !$broadcast->show) {
        continue;
    }

    $makers = zw_acf_rows($broadcast->show->meta('fm_show_makers'));
    $photos = array_values(array_filter(array_column($makers, 'fm_show_maker_foto')));

    $upcoming[] = [
        'broadcast' => $broadcast,
        'show' => $broadcast->show,
        'label' => $whenLabel($broadcast->start),
        'makers' => array_values(array_filter(array_column($makers, 'fm_show_maker_naam'))),
        'photo' => $photos[0] ?? null,
    ];
}

$context['upcoming'] = $upcoming;

// One repeater holds every way to receive the station; the page shows a section per medium.
$frequencies = ['ether' => [], 'dab' => [], 'kabel' => []];
foreach (zw_acf_rows(get_field('radio_frequenties', 'option')) as $row) {
    $medium = match ($row['radio_frequenties_medium'] ?? null) {
        'Ether' => 'ether',
        'DAB+' => 'dab',
        'Kabel' => 'kabel',
        default => null,
    };

    $value = trim((string)($row['radio_frequenties_frequentie'] ?? ''));
    if ($medium === null || $value === '') {
        continue;
    }

    $frequencies[$medium][] = [
        'value' => $value,
        'place' => trim((string)($row['radio_frequenties_plaats'] ?? '')),
    ];
}

$context['frequencies'] = $frequencies;
$context['has_frequencies'] = $frequencies['ether'] || $frequencies['dab'] || $frequencies['kabel'];

$context['recent'] = AeronToolbox::recentTracks();

$context['breadcrumb_separator'] = class_exists('WPSEO_Options') ? WPSEO_Options::get('breadcrumbs-sep', '/') : '/';
$context['fm_post_type'] = get_post_type_object('fm');

zw_require_videojs();
Timber::render(['page-fm-player.twig', 'page.twig'], $context);
