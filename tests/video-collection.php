<?php

/**
 * Tests that Bunny video preprocessing tolerates nullable API fields.
 */

require __DIR__ . '/../vendor/autoload.php';

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

$failed = 0;
foreach ($cases as $name => [$video, $expected]) {
    VideoCollection::preprocessOne($video);
    if ($video->_description !== $expected || $video->_broadcastTimestamp !== null) {
        echo 'FAIL: ' . $name . PHP_EOL;
        $failed++;
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
        echo 'FAIL: ' . $name . PHP_EOL;
        $failed++;
    }
}

if ($failed) {
    exit(1);
}

echo "OK: video preprocessing tolerates nullable Bunny metaTags and MP4 selection skips missing renditions\n";
