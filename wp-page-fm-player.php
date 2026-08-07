<?php

/**
 * Template Name: FM Player
 *
 * The live radio page: what is on air now, what plays next and where to find the station.
 * The now playing card is refreshed from the live metadata websocket by static/fm-live.js.
 */

use Carbon\Carbon;
use Streekomroep\BroadcastDay;
use Streekomroep\BroadcastSchedule;

$context = Timber::context();
$context['post'] = Timber::get_post();

$schedule = new BroadcastSchedule();
$current = $schedule->getCurrentRadioBroadcast();

$context['current'] = $current;
$context['broadcast_data_url'] = rest_url('zw/v1/broadcast_data');
$context['schedule_refresh_after'] = $current ? max(
    1,
    $current->end->timestamp - Carbon::now(wp_timezone())->timestamp
) : 30;

// Filler broadcasts carry only a name, so the progress bar belongs to real shows.
$context['live_show'] = $current?->show;
if ($context['live_show']) {
    $now = Carbon::now(wp_timezone());
    $length = max(1, $current->start->diffInSeconds($current->end));
    $elapsed = min($length, max(0, $current->start->diffInSeconds($now)));
    $remaining = (int)ceil(($length - $elapsed) / 60);
    $freshPeriod = min(15 * 60, $length * 0.15);

    $context['progress'] = (int)round($elapsed / $length * 100);
    if ($elapsed < $freshPeriod) {
        $context['progress_label'] = 'net gestart';
    } elseif ($remaining >= 60) {
        $hours = intdiv($remaining, 60);
        $minutes = $remaining % 60;
        if ($minutes > 0) {
            $context['progress_label'] = sprintf('%d uur %d min te gaan', $hours, $minutes);
        } else {
            $context['progress_label'] = sprintf('%d uur te gaan', $hours);
        }
    } else {
        $context['progress_label'] = sprintf('%d min te gaan', $remaining);
    }
}

/** Labels shows beyond today; today's shows are already covered by the "Straks" section heading. */
$whenLabel = function (Carbon $start) {
    if ($start->isToday()) {
        return null;
    }

    return $start->isTomorrow() ? 'morgen' : BroadcastDay::WEEKDAY_NAMES[$start->dayOfWeekIso];
};

$context['upcoming'] = array_map(fn ($broadcast) => [
    'broadcast' => $broadcast,
    'label' => $whenLabel($broadcast->start),
], $schedule->getUpcomingRadioBroadcasts(2));

// VideoJS tries sources in order, so list them by preference.
$streamTypes = [
    'radio_webplayer_aac_stream' => 'audio/mp4',
    'radio_webplayer_mp3_stream' => 'audio/mpeg',
    'radio_webplayer_ogg_stream' => 'audio/ogg',
    'radio_webplayer_hls_stream' => 'application/x-mpegURL',
];

$context['stream_sources'] = [];
foreach ($streamTypes as $field => $mimeType) {
    $url = $context['options'][$field] ?? null;
    if ($url) {
        $context['stream_sources'][] = ['url' => $url, 'type' => $mimeType];
    }
}

// One repeater holds every way to receive the station; the page shows a section per medium.
$groups = [
    'Ether' => ['badge' => 'FM', 'title' => 'Via de ether', 'unit' => 'FM', 'channels' => []],
    'DAB+' => ['badge' => 'DAB+', 'title' => 'Digitale radio', 'unit' => '', 'channels' => []],
    'Kabel' => ['badge' => 'Kabel', 'title' => 'Via je aanbieder', 'unit' => '', 'channels' => []],
];

foreach (zw_acf_rows($context['options']['radio_frequenties'] ?? null) as $row) {
    $medium = $row['radio_frequenties_medium'] ?? null;
    if (!isset($groups[$medium])) {
        continue;
    }

    // The field asks for the number only, but older ether rows may still carry an "FM" suffix.
    $value = trim((string)($row['radio_frequenties_frequentie'] ?? ''));
    if ($medium === 'Ether') {
        $value = trim(preg_replace('/\s*FM$/i', '', $value));
    }

    if ($value === '') {
        continue;
    }

    $groups[$medium]['channels'][] = [
        'value' => $value,
        // Ether rows without a place stay bare; other media fall back to their medium name.
        'place' => trim((string)($row['radio_frequenties_plaats'] ?? '')) ?: ($medium === 'Ether' ? '' : $medium),
    ];
}

$context['frequency_groups'] = array_values(array_filter($groups, fn ($group) => $group['channels']));

$context['fm_post_type'] = get_post_type_object('fm');

zw_require_videojs();
Timber::render(['page-fm-player.twig', 'page.twig'], $context);
