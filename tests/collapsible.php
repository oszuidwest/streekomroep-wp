<?php

/**
 * Tests collapsible section normalization and feed rendering.
 */

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/bootstrap-html-api.php';

use Streekomroep\CollapsibleNormalizer;

$nl = "\n";
$section = fn (string $title, string ...$items) => '<div class="collapsible">' . $nl . '<h3 class="collapsible-title">' . $title . '</h3>' . $nl . implode('', $items) . '</div>';
$item = fn (string $heading, string $body, bool $open = false) => '<details class="collapsible-item"' . ($open ? ' open' : '') . '>' . $nl . '<summary>' . $heading . '</summary>' . $nl . $body . ($body === '' ? '' : $nl) . '</details>' . $nl;

$canonical = '<p>Intro</p>' . $nl . $nl . $section('Titel', $item('Kop', 'Alinea een.' . $nl . $nl . 'Alinea <strong>twee</strong> met <a href="https://example.com">link</a>.'), $item('Twee', '<ul>' . $nl . '<li>a</li>' . $nl . '</ul>', true)) . $nl . $nl . '<p>Slot</p>';

$normalizeCases = [
    'canonical editor output is unchanged' => [$canonical, $canonical],
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
    'empty item disappears' => [
        '<div class="collapsible"><h3 class="collapsible-title">T</h3><details class="collapsible-item"><summary></summary></details><details class="collapsible-item"><summary>K</summary>A</details></div>',
        $section('T', $item('K', 'A')),
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
    'closing details ends the item even inside unclosed divs' => [
        '<div class="collapsible"><h3 class="collapsible-title">T</h3><details class="collapsible-item"><summary>K</summary>A<div><div>B</details><p>Na</p></div>',
        $section('T', $item('K', 'A' . $nl . $nl . 'B')) . $nl . $nl . '<p>Na</p>',
    ],
    'unclosed item ends with the section' => [
        '<div class="collapsible"><h3 class="collapsible-title">T</h3><details class="collapsible-item"><summary>K</summary>A</div><p>Na</p>',
        $section('T', $item('K', 'A')) . '<p>Na</p>',
    ],
    'empty paragraphs around and inside a section are dropped' => [
        '<p>A</p>' . $nl . $nl . '&nbsp;' . $nl . $nl . '<div class="collapsible"><h3 class="collapsible-title">T</h3><details class="collapsible-item"><summary>K</summary>Regel' . $nl . $nl . '&nbsp;' . $nl . $nl . '<p>&nbsp;</p>' . $nl . 'Nog een regel met 10&nbsp;km</details></div>' . $nl . '&nbsp;' . $nl . $nl . '<p>&nbsp;</p>' . $nl . '<p>B</p>',
        '<p>A</p>' . $nl . $nl . $section('T', $item('K', 'Regel' . $nl . $nl . 'Nog een regel met 10&nbsp;km')) . $nl . '<p>B</p>',
    ],
    'CRLF line endings from the browser are handled' => [
        "<p>A</p>\r\n\r\n<div class=\"collapsible\">\r\n<h3 class=\"collapsible-title\">T</h3>\r\n<details class=\"collapsible-item\"><summary>K</summary>Regel\r\n\r\n&nbsp;\r\n\r\nTwee</details>\r\n<details class=\"collapsible-item\"><summary>Leeg</summary>&nbsp;\r\n\r\n</details></div>\r\n&nbsp;\r\n\r\n<p>B</p>",
        "<p>A</p>\r\n\r\n" . $section('T', $item('K', 'Regel' . $nl . $nl . 'Twee'), $item('Leeg', '')) . "\r\n\r\n<p>B</p>",
    ],
    'trailing empty paragraph after the last section goes' => [
        $section('T', $item('K', 'A')) . $nl . '&nbsp;',
        $section('T', $item('K', 'A')),
    ],
    'invalid UTF-8 is not discarded when Unicode cleanup fails' => [
        '<div class="collapsible"><h3 class="collapsible-title">T</h3><details class="collapsible-item"><summary>K</summary>A' . "\xFF" . '</details></div>',
        $section('T', $item('K', 'A' . "\xFF")),
    ],
    'greater-than inside an attribute value is re-escaped' => [
        '<div class="collapsible"><h3 class="collapsible-title">T</h3><details class="collapsible-item"><summary>K</summary><p>Zie <a href="https://example.com/?q=a>b" target="_blank">link</a></p></details></div>',
        $section('T', $item('K', '<p>Zie <a href="https://example.com/?q=a&gt;b" target="_blank">link</a></p>')),
    ],
    'stray less-than in text is kept' => [
        '<div class="collapsible"><h3 class="collapsible-title">T</h3><details class="collapsible-item"><summary>K</summary><p>1 < 2 is waar</p><p>Na</p></details></div>',
        $section('T', $item('K', '<p>1 < 2 is waar</p><p>Na</p>')),
    ],
    'iframe fallback text is kept' => [
        '<div class="collapsible"><h3 class="collapsible-title">T</h3><details class="collapsible-item"><summary>K</summary><iframe src="https://player.mediadelivery.net/play/1/abc">Video</iframe></details></div>',
        $section('T', $item('K', '<iframe src="https://player.mediadelivery.net/play/1/abc">Video</iframe>')),
    ],
    'comments inside an item are dropped' => [
        '<div class="collapsible"><h3 class="collapsible-title">T</h3><details class="collapsible-item"><summary>K</summary><!-- <b>noot</b> --><p>A</p></details></div>',
        $section('T', $item('K', '<p>A</p>')),
    ],
    'unclosed paragraphs and list items are closed' => [
        '<div class="collapsible"><h3 class="collapsible-title">T</h3><details class="collapsible-item"><summary>K</summary><p>Een<p>Twee<ul><li>a<li>b</ul></details></div>',
        $section('T', $item('K', '<p>Een</p><p>Twee</p><ul><li>a</li><li>b</li></ul>')),
    ],
    'boolean and unquoted attributes are serialized' => [
        '<div class="collapsible"><h3 class="collapsible-title">T</h3><details class="collapsible-item"><summary>K</summary><iframe src=https://player.mediadelivery.net/play/1/abc allowfullscreen></iframe><img src=x.jpg alt=\'Foto "1"\'></details></div>',
        $section('T', $item('K', '<iframe src="https://player.mediadelivery.net/play/1/abc" allowfullscreen></iframe><img src="x.jpg" alt="Foto &quot;1&quot;">')),
    ],
    'section inside a quote ends with the quote' => [
        '<blockquote><div class="collapsible"><h3 class="collapsible-title">T</h3><details class="collapsible-item"><summary>K</summary>A</details></blockquote><p>Na</p>',
        '<blockquote>' . $section('T', $item('K', 'A')) . '</blockquote><p>Na</p>',
    ],
    'markup the parser does not support is left unchanged' => [
        '<p><b>x</p><b>y</b><div class="collapsible"><h1 class="collapsible-title">T</h1><details class="collapsible-item"><summary>K</summary>A</details></div>',
        '<p><b>x</p><b>y</b><div class="collapsible"><h1 class="collapsible-title">T</h1><details class="collapsible-item"><summary>K</summary>A</details></div>',
    ],
    'unterminated tag after a section is kept' => [
        $section('T', $item('K', 'A')) . '<div',
        $section('T', $item('K', 'A')) . '<div',
    ],
    'content without sections is untouched' => ['<h1>Gewoon</h1><details><summary>x</summary>y</details>', '<h1>Gewoon</h1><details><summary>x</summary>y</details>'],
];

$flattenCases = [
    'section with title and items' => [
        '<p>Intro</p>' . $nl . $section('Hoe zat het?', $item('Een', '<p>Tekst</p>')) . $nl . '<p>Slot</p>',
        '<p>Intro</p>' . $nl . '<div class="collapsible">' . $nl . '<h3 class="collapsible-title">Hoe zat het?</h3>' . $nl . '<h4>Een</h4>' . $nl . '<p>Tekst</p>' . $nl . $nl . '</div>' . $nl . '<p>Slot</p>',
    ],
    'open item with inline markup in the heading' => [
        '<details class="collapsible-item" open><summary>Kop <em>x</em></summary><p>A</p></details><details class="collapsible-item"><summary>Twee</summary><p>B</p></details>',
        '<h4>Kop <em>x</em></h4><p>A</p><h4>Twee</h4><p>B</p>',
    ],
    'nested details inside an item stays in the body' => [
        '<details class="collapsible-item"><summary>A</summary><details><summary>B</summary>x</details>y</details><p>Z</p>',
        '<h4>A</h4><details><summary>B</summary>x</details>y<p>Z</p>',
    ],
    'item whose summary is not the first child is left alone' => [
        '<details class="collapsible-item"><details>x</details><summary>S</summary>b</details>',
        '<details class="collapsible-item"><details>x</details><summary>S</summary>b</details>',
    ],
    'item without a summary is left alone' => [
        '<details class="collapsible-item"><p>A</p></details>',
        '<details class="collapsible-item"><p>A</p></details>',
    ],
    'unrelated details element next to an item' => [
        '<details class="collapsible-item"><summary>K</summary><p>A</p></details><details><summary>Ander</summary><p>Blijft</p></details>',
        '<h4>K</h4><p>A</p><details><summary>Ander</summary><p>Blijft</p></details>',
    ],
];

$failures = [];
foreach (['normalize' => $normalizeCases, 'flatten' => $flattenCases] as $method => $cases) {
    foreach ($cases as $label => [$input, $expected]) {
        $actual = CollapsibleNormalizer::$method($input);
        if ($actual !== $expected) {
            $failures[] = sprintf("%s %s:\n  expected %s\n  got      %s", $method, $label, json_encode($expected), json_encode($actual));
        }
    }
}

if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo 'OK: collapsible sections normalize to their canonical form and flatten in feeds' . PHP_EOL;
