<?php

/**
 * Regression coverage for flattening collapsible sections in feeds.
 *
 * Run with: composer test:uitklap
 */

function add_filter(string $hook, callable $callback, int $priority = 10, int $accepted_args = 1): bool
{
    return true;
}

require __DIR__ . '/../lib/uitklap.php';

$failures = [];

$cases = [
    'closed section' => [
        '<p>Intro</p>' . PHP_EOL . '<details class="uitklap">' . PHP_EOL . '<summary>Hoe zat het?</summary>' . PHP_EOL . '<p>Tekst</p>' . PHP_EOL . '</details>' . PHP_EOL . '<p>Slot</p>',
        '<p>Intro</p>' . PHP_EOL . '<h3>Hoe zat het?</h3>' . PHP_EOL . '<p>Tekst</p>' . PHP_EOL . PHP_EOL . '<p>Slot</p>',
    ],
    'open section with inline markup in the heading' => [
        '<details class="uitklap" open><summary>Kop <em>x</em></summary><p>A</p></details><details class="uitklap"><summary>Twee</summary><p>B</p></details>',
        '<h3>Kop <em>x</em></h3><p>A</p><h3>Twee</h3><p>B</p>',
    ],
    'unrelated details element' => [
        '<details><summary>Ander</summary><p>Blijft</p></details>',
        '<details><summary>Ander</summary><p>Blijft</p></details>',
    ],
    'plain content' => ['<p>Niets</p>', '<p>Niets</p>'],
];

foreach ($cases as $label => [$input, $expected]) {
    $actual = zw_uitklap_flatten($input);
    if ($actual !== $expected) {
        $failures[] = sprintf('%s: expected %s, got %s', $label, $expected, $actual);
    }
}

if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo 'OK: collapsible sections flatten to headings in feeds' . PHP_EOL;
