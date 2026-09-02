<?php

/**
 * Regression coverage for rewriting collapsible sections into their canonical form on save.
 *
 * Run with: composer test:collapsible
 */

require __DIR__ . '/../vendor/autoload.php';

use Streekomroep\CollapsibleNormalizer;

$nl = PHP_EOL;
$section = fn (string $title, string ...$items) => '<div class="collapsible">' . $nl . '<h3 class="collapsible-title">' . $title . '</h3>' . $nl . implode('', $items) . '</div>';
$item = fn (string $heading, string $body, bool $open = false) => '<details class="collapsible-item"' . ($open ? ' open' : '') . '>' . $nl . '<summary>' . $heading . '</summary>' . $nl . $body . ($body === '' ? '' : $nl) . '</details>' . $nl;

$cases = [
    'canonical editor output is unchanged' => [
        '<p>Intro</p>' . $nl . $nl . $section('Titel', $item('Kop', 'Alinea een.' . $nl . $nl . 'Alinea <strong>twee</strong> met <a href="https://example.com">link</a>.'), $item('Twee', '<ul>' . $nl . '<li>a</li>' . $nl . '</ul>', true)) . $nl . $nl . '<p>Slot</p>',
        '<p>Intro</p>' . $nl . $nl . $section('Titel', $item('Kop', 'Alinea een.' . $nl . $nl . 'Alinea <strong>twee</strong> met <a href="https://example.com">link</a>.'), $item('Twee', '<ul>' . $nl . '<li>a</li>' . $nl . '</ul>', true)) . $nl . $nl . '<p>Slot</p>',
    ],
    'title demoted to h1 with inline markup' => [
        '<div class="collapsible"><h1 class="collapsible-title">Titel <em>met</em> <a href="#">link</a></h1><details class="collapsible-item"><summary>Kop</summary>Tekst</details></div>',
        $section('Titel met link', $item('Kop', 'Tekst')),
    ],
    'title turned into a paragraph with extra classes' => [
        '<div class="collapsible"><p class="foo collapsible-title" id="x">Titel</p><details class="collapsible-item"><summary>K</summary>T</details></div>',
        $section('Titel', $item('K', 'T')),
    ],
    'heading turned into h2 is promoted back to summary' => [
        '<div class="collapsible"><h3 class="collapsible-title">T</h3><details class="collapsible-item" open=""><h2>Kop</h2>Tekst</details></div>',
        $section('T', $item('Kop', 'Tekst', true)),
    ],
    'item without any heading leaves the section' => [
        '<div class="collapsible"><h3 class="collapsible-title">T</h3><details class="collapsible-item"><summary>K</summary>A</details><details class="collapsible-item">Losse tekst</details></div>',
        $section('T', $item('K', 'A')) . $nl . $nl . 'Losse tekst',
    ],
    'stray paragraphs inside the section move after it' => [
        '<div class="collapsible"><h3 class="collapsible-title">T</h3>' . $nl . $nl . 'Verdwaald' . $nl . $nl . '<details class="collapsible-item"><summary>K</summary>A</details>' . $nl . $nl . '<p>Ook verdwaald</p></div>' . $nl . $nl . 'Erna',
        $section('T', $item('K', 'A')) . $nl . $nl . 'Verdwaald' . $nl . $nl . '<p>Ook verdwaald</p>' . $nl . $nl . 'Erna',
    ],
    'headings, quotes and tables inside an item become paragraphs' => [
        '<div class="collapsible"><h3 class="collapsible-title">T</h3><details class="collapsible-item"><summary>K</summary>Eerst<h2>Tussenkop</h2><blockquote>Quote</blockquote><table><tr><td>cel</td></tr></table><span class="x">span</span> na</details></div>',
        $section('T', $item('K', 'Eerst' . $nl . $nl . 'Tussenkop' . $nl . $nl . 'Quote' . $nl . $nl . 'cel' . $nl . $nl . 'span na')),
    ],
    'nested section inside an item dissolves into text' => [
        '<div class="collapsible"><h3 class="collapsible-title">Buiten</h3><details class="collapsible-item"><summary>K</summary>Voor<div class="collapsible"><h3 class="collapsible-title">Binnen</h3><details class="collapsible-item"><summary>Sub</summary>Subtekst</details></div>Na</details></div>',
        $section('Buiten', $item('K', 'Voor' . $nl . $nl . 'Binnen' . $nl . $nl . 'Sub' . $nl . $nl . 'Subtekst' . $nl . $nl . 'Na')),
    ],
    'section without items keeps its title as text' => [
        '<p>A</p><div class="collapsible"><h3 class="collapsible-title">Alleen titel</h3></div><p>B</p>',
        '<p>A</p><strong>Alleen titel</strong><p>B</p>',
    ],
    'section without title gets an empty one' => [
        '<div class="collapsible"><details class="collapsible-item"><summary>K</summary>A</details></div>',
        $section('', $item('K', 'A')),
    ],
    'unclosed item ends with the section' => [
        '<div class="collapsible"><h3 class="collapsible-title">T</h3><details class="collapsible-item"><summary>K</summary>A</div><p>Na</p>',
        $section('T', $item('K', 'A')) . '<p>Na</p>',
    ],
    'content without sections is untouched' => ['<h1>Gewoon</h1><details><summary>x</summary>y</details>', '<h1>Gewoon</h1><details><summary>x</summary>y</details>'],
];

$failures = [];
foreach ($cases as $label => [$input, $expected]) {
    $actual = CollapsibleNormalizer::normalize($input);
    if ($actual !== $expected) {
        $failures[] = sprintf("%s:\n  expected %s\n  got      %s", $label, json_encode($expected), json_encode($actual));
    }
}

if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo 'OK: collapsible sections normalize to their canonical form' . PHP_EOL;
