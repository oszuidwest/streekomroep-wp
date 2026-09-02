<?php

/**
 * Regression coverage for collapsible sections: feed flattening and save-time normalization.
 *
 * Run with: composer test:collapsible
 */

function add_filter(string $hook, callable $callback, int $priority = 10, int $accepted_args = 1): bool
{
    return true;
}

require __DIR__ . '/../lib/collapsible.php';

$failures = [];

$cases = [
    'section with title and items' => [
        '<p>Intro</p>' . PHP_EOL . '<div class="collapsible">' . PHP_EOL . '<h3 class="collapsible-title">Hoe zat het?</h3>' . PHP_EOL . '<details class="collapsible-item">' . PHP_EOL . '<summary>Een</summary>' . PHP_EOL . '<p>Tekst</p>' . PHP_EOL . '</details>' . PHP_EOL . '</div>' . PHP_EOL . '<p>Slot</p>',
        '<p>Intro</p>' . PHP_EOL . '<div class="collapsible">' . PHP_EOL . '<h3 class="collapsible-title">Hoe zat het?</h3>' . PHP_EOL . '<h4>Een</h4>' . PHP_EOL . '<p>Tekst</p>' . PHP_EOL . PHP_EOL . '</div>' . PHP_EOL . '<p>Slot</p>',
    ],
    'open item with inline markup in the heading' => [
        '<details class="collapsible-item" open><summary>Kop <em>x</em></summary><p>A</p></details><details class="collapsible-item"><summary>Twee</summary><p>B</p></details>',
        '<h4>Kop <em>x</em></h4><p>A</p><h4>Twee</h4><p>B</p>',
    ],
    'unrelated details element' => [
        '<details><summary>Ander</summary><p>Blijft</p></details>',
        '<details><summary>Ander</summary><p>Blijft</p></details>',
    ],
    'plain content' => ['<p>Niets</p>', '<p>Niets</p>'],
];

foreach ($cases as $label => [$input, $expected]) {
    $actual = zw_collapsible_flatten($input);
    if ($actual !== $expected) {
        $failures[] = sprintf('%s: expected %s, got %s', $label, $expected, $actual);
    }
}

$normalize_cases = [
    'title demoted to h1' => [
        '<div class="collapsible"><h1 class="collapsible-title">Titel</h1><details class="collapsible-item"><summary>Kop</summary>Tekst</details></div>',
        '<div class="collapsible"><h3 class="collapsible-title">Titel</h3><details class="collapsible-item"><summary>Kop</summary>Tekst</details></div>',
    ],
    'title turned into a paragraph with extra attributes' => [
        '<div class="collapsible"><p class="foo collapsible-title" id="x">Titel <em>a</em></p></div>',
        '<div class="collapsible"><h3 class="collapsible-title">Titel <em>a</em></h3></div>',
    ],
    'heading turned into h2 is promoted back to summary' => [
        '<details class="collapsible-item" open><h2>Kop</h2>Tekst</details>',
        '<details class="collapsible-item" open><summary>Kop</summary>Tekst</details>',
    ],
    'item without any heading becomes plain text' => [
        '<p>A</p><details class="collapsible-item">Tekst zonder kop</details><p>B</p>',
        '<p>A</p>Tekst zonder kop<p>B</p>',
    ],
    'intact section is untouched' => [
        '<div class="collapsible">' . PHP_EOL . '<h3 class="collapsible-title">T</h3>' . PHP_EOL . '<details class="collapsible-item">' . PHP_EOL . '<summary>K</summary>' . PHP_EOL . 'Tekst' . PHP_EOL . '</details>' . PHP_EOL . '</div>',
        '<div class="collapsible">' . PHP_EOL . '<h3 class="collapsible-title">T</h3>' . PHP_EOL . '<details class="collapsible-item">' . PHP_EOL . '<summary>K</summary>' . PHP_EOL . 'Tekst' . PHP_EOL . '</details>' . PHP_EOL . '</div>',
    ],
    'content without sections' => ['<h1>Gewoon</h1>', '<h1>Gewoon</h1>'],
];

foreach ($normalize_cases as $label => [$input, $expected]) {
    $actual = zw_collapsible_normalize($input);
    if ($actual !== $expected) {
        $failures[] = sprintf('normalize %s: expected %s, got %s', $label, $expected, $actual);
    }
}

if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo 'OK: collapsible sections flatten in feeds and normalize on save' . PHP_EOL;
