<?php

/**
 * Template Name: FM Player
 */

use Carbon\Carbon;
use Streekomroep\BroadcastDataController;
use Streekomroep\BroadcastSchedule;

$context = Timber::context();
$context['post'] = Timber::get_post();

$schedule = new BroadcastSchedule();
$current = $schedule->getCurrentRadioBroadcast();

// Keep initial rendering and REST refreshes on the same schedule contract.
$context['broadcast'] = $current?->toArray();
$context['broadcast_data_url'] = BroadcastDataController::url();
$context['schedule_refresh_after'] = $schedule->getRefreshAfter($current);

// Filler slots have no show and therefore no programme progress bar.
if ($current?->show) {
    $now = Carbon::now(wp_timezone());
    $length = max(1, $current->start->diffInSeconds($current->end));
    $elapsed = min($length, max(0, $current->start->diffInSeconds($now)));
    $context['progress'] = (int)round($elapsed / $length * 100);
}

$context['upcoming'] = array_map(fn ($broadcast) => $broadcast->toArray(), $schedule->getUpcomingRadioBroadcasts(2));

// The browser walks these in order and moves to the next candidate when one will not load, so
// each stream has to be announced with the type the server actually sends. Icecast serves the AAC
// mount as raw ADTS (Content-Type: audio/aac); calling that audio/mp4 makes a browser accept bytes
// it then hands to an MP4 demuxer, which strands devices that are strict about it on a dead source
// instead of letting them fall through to MP3.
$streamTypes = [
    'radio_webplayer_aac_stream' => 'audio/aac',
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

$context['media_artwork'] = [];
$artworkUrl = $context['options']['radio_fallback_img']['url'] ?? null;
if ($artworkUrl && $context['stream_sources']) {
    foreach ([96, 192, 512] as $size) {
        $context['media_artwork'][] = [
            'src' => zw_imgproxy($artworkUrl, $size, $size),
            'sizes' => $size . 'x' . $size,
        ];
    }
}

$groups = [
    'Ether' => ['title' => 'FM', 'channels' => []],
    'DAB+' => ['title' => 'DAB+', 'channels' => []],
    'Kabel' => ['title' => 'Kabel', 'channels' => []],
];

foreach (zw_acf_rows($context['options']['radio_frequenties'] ?? null) as $row) {
    $medium = $row['radio_frequenties_medium'] ?? null;
    if (!isset($groups[$medium])) {
        continue;
    }

    // Normalize legacy ether values that include the unit.
    $value = trim((string)($row['radio_frequenties_frequentie'] ?? ''));
    if ($medium === 'Ether') {
        $value = trim(preg_replace('/\s*FM$/i', '', $value));
    }

    if ($value === '') {
        continue;
    }

    $groups[$medium]['channels'][] = [
        'value' => $value,
        // Only non-ether media use the medium name as a missing place label.
        'place' => trim((string)($row['radio_frequenties_plaats'] ?? '')) ?: ($medium === 'Ether' ? '' : $medium),
    ];
}

$context['frequency_groups'] = array_values(array_filter($groups, fn ($group) => $group['channels']));

Timber::render(['page-fm-player.twig', 'page.twig'], $context);
