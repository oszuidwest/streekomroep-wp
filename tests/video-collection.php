<?php

/**
 * Tests Bunny video preprocessing, MP4 selection and episode lookup.
 */

require __DIR__ . '/../vendor/autoload.php';

const ZW_TV_META_VIDEOS = 'bunny_data';
const ZW_BUNNY_LIBRARY_TV = -1;

$post_meta = [];
$post_meta_reads = 0;

function get_field(string $name, string $post_id): mixed
{
    return ['bunny_cdn_library_id' => 1, 'bunny_cdn_hostname' => 'https://cdn', 'bunny_cdn_api_key' => 'key'][$name] ?? null;
}

function get_post_meta(int $id, string $key, bool $single): mixed
{
    global $post_meta, $post_meta_reads;
    $post_meta_reads++;
    return $post_meta[$id][$key] ?? '';
}

use Streekomroep\BunnyCredentials;
use Streekomroep\Video;
use Streekomroep\VideoCollection;

$cases = [
    'null metaTags' => [(object) ['metaTags' => null], ''],
    'missing metaTags' => [(object) [], ''],
    'object metaTags' => [(object) ['metaTags' => (object) ['property' => 'description']], ''],
    'null metaTag entry' => [(object) ['metaTags' => [null, (object) ['property' => 'description', 'value' => 'Tekst']]], 'Tekst'],
    'plain description' => [(object) ['metaTags' => [(object) ['property' => 'description', 'value' => 'Tekst']]], 'Tekst'],
];

$failures = [];
foreach ($cases as $name => [$video, $expected]) {
    VideoCollection::preprocessOne($video);
    if ($video->_description !== $expected || $video->_broadcastTimestamp !== null) {
        $failures[] = sprintf('%s: expected description %s without timestamp, got %s with timestamp %s', $name, json_encode($expected), json_encode($video->_description), json_encode($video->_broadcastTimestamp));
    }
}

$credentials = new BunnyCredentials(1, 'https://cdn', 'key');
$mp4Cases = [
    'null resolutions' => [null, null],
    'empty resolutions' => ['', null],
    'garbage resolutions' => ['foo', null],
    'garbage beside a size' => ['1080p,foo', 'https://cdn/g/play_1080p.mp4'],
    'largest up to 720p' => ['240p,480p,720p,1080p', 'https://cdn/g/play_720p.mp4'],
    'only above 720p' => ['1080p', 'https://cdn/g/play_1080p.mp4'],
];
foreach ($mp4Cases as $name => [$resolutions, $expected]) {
    $video = new Video($credentials, (object) ['guid' => 'g', 'availableResolutions' => $resolutions]);
    $types = array_column($video->getSources(), 'type');
    if ($video->getMP4Url() !== $expected || in_array('video/mp4', $types, true) !== ($expected !== null)) {
        $failures[] = sprintf('%s: expected %s, got %s with sources %s', $name, json_encode($expected), json_encode($video->getMP4Url()), json_encode($types));
    }
}

$episode = fn (string $guid, ?int $timestamp, int $status = Video::STATUS_FINISHED) => (object) [
    'guid' => $guid,
    'status' => $status,
    '_broadcastDate' => $timestamp === null ? null : date('c', $timestamp),
    '_broadcastTimestamp' => $timestamp,
];
$post_meta[10][ZW_TV_META_VIDEOS] = [
    null,
    'garbage',
    $episode('old', 100),
    $episode('dup', 200),
    $episode('dup', 300),
    $episode('dup', 400, 3),
    $episode('dup', time() + 86400),
];
$post_meta[11][ZW_TV_META_VIDEOS] = 'garbage';

$ids = array_map(fn ($video) => $video->getId() . '@' . $video->getBroadcastDate()?->getTimestamp(), VideoCollection::forTvShow(10));
if ($ids !== ['dup@300', 'dup@200', 'old@100']) {
    $failures[] = 'episode list: expected ["dup@300","dup@200","old@100"], got ' . json_encode($ids);
}

$duplicate = VideoCollection::findVideo(10, 'dup');
if ($duplicate !== VideoCollection::forTvShow(10)[0]) {
    $failures[] = 'duplicate GUID: expected the newest available episode from the sorted list';
}

if (VideoCollection::findVideo(10, 'missing') !== null || VideoCollection::forTvShow(11) !== []) {
    $failures[] = 'lookup: expected no episode for an unknown GUID or non-array meta';
}

if ($post_meta_reads !== 2) {
    $failures[] = 'request cache: expected one meta read per show, got ' . $post_meta_reads;
}

if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "OK: video preprocessing tolerates nullable Bunny fields, MP4 selection skips missing renditions and lookup matches the sorted episode list\n";
