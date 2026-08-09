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
    // TEMPORARY: prefer HLS on the staging-only diagnostics branch for comparison with AAC.
    'radio_webplayer_hls_stream' => 'application/x-mpegURL',
    'radio_webplayer_aac_stream' => 'audio/aac',
    'radio_webplayer_mp3_stream' => 'audio/mpeg',
    'radio_webplayer_ogg_stream' => 'audio/ogg',
];

$context['stream_sources'] = [];
$debugStream = isset($_GET['debug_stream']) ? sanitize_key(wp_unslash($_GET['debug_stream'])) : '';
$hlsUrl = $context['options']['radio_webplayer_hls_stream'] ?? null;
$debugSources = [];

if ($hlsUrl) {
    $hlsBaseUrl = trailingslashit((string) preg_replace('#/[^/]+$#', '', $hlsUrl));
    $debugSources = [
        'hls-master' => ['url' => $hlsUrl, 'type' => 'application/x-mpegURL'],
        'hls-he-aac' => ['url' => $hlsBaseUrl . 'aac_48.m3u8', 'type' => 'application/x-mpegURL'],
        'hls-aac-96' => ['url' => $hlsBaseUrl . 'aac_96.m3u8', 'type' => 'application/x-mpegURL'],
        'hls-aac-192' => ['url' => $hlsBaseUrl . 'aac_192.m3u8', 'type' => 'application/x-mpegURL'],
    ];
}

$debugSources['aac'] = [
    'url' => $context['options']['radio_webplayer_aac_stream'] ?? null,
    'type' => 'audio/aac',
];
$debugSources['mp3'] = [
    'url' => $context['options']['radio_webplayer_mp3_stream'] ?? null,
    'type' => 'audio/mpeg',
];
$debugSources['hls-aac-lc'] = [
    'url' => get_theme_file_uri('static/fm-hls-aac-lc.m3u8'),
    'type' => 'application/x-mpegURL',
];

$debugLabels = [
    'hls-master' => 'HLS master',
    'hls-aac-lc' => 'HLS master LC-only',
    'hls-he-aac' => 'HLS HE-AAC 48',
    'hls-aac-96' => 'HLS AAC-LC 96',
    'hls-aac-192' => 'HLS AAC-LC 192',
    'aac' => 'Icecast AAC',
    'mp3' => 'Icecast MP3',
];
$context['debug_stream'] = $debugStream;
$context['debug_stream_options'] = [];
foreach ($debugLabels as $key => $label) {
    if (!empty($debugSources[$key]['url'])) {
        $context['debug_stream_options'][] = [
            'key' => $key,
            'label' => $label,
            'url' => add_query_arg('debug_stream', $key, get_permalink()),
        ];
    }
}

if (isset($debugSources[$debugStream]) && $debugSources[$debugStream]['url']) {
    $context['stream_sources'][] = $debugSources[$debugStream];
} else {
    foreach ($streamTypes as $field => $mimeType) {
        $url = $context['options'][$field] ?? null;
        if ($url) {
            $context['stream_sources'][] = ['url' => $url, 'type' => $mimeType];
        }
    }
}

$context['show_stream_diagnostics'] = zw_fm_stream_diagnostics_enabled();

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
    'Ether' => ['badge' => 'FM', 'title' => 'Via de ether', 'unit' => 'FM', 'channels' => []],
    'DAB+' => ['badge' => 'DAB+', 'title' => 'Digitale radio', 'unit' => '', 'channels' => []],
    'Kabel' => ['badge' => 'Kabel', 'title' => 'Via je aanbieder', 'unit' => '', 'channels' => []],
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
