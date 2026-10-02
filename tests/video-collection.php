<?php

/**
 * Tests that Bunny video preprocessing tolerates nullable API fields.
 */

require __DIR__ . '/../vendor/autoload.php';

use Streekomroep\VideoCollection;

$cases = [
    'null metaTags' => [(object) ['metaTags' => null], ''],
    'missing metaTags' => [(object) [], ''],
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

if ($failed) {
    exit(1);
}

echo "OK: video preprocessing tolerates nullable Bunny metaTags\n";
