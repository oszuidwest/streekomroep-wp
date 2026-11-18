<?php

/**
 * Regression coverage for the pagination markup structure.
 *
 * A list may only contain list items: the mobile "Pagina x van y" indicator
 * must not sit directly inside the <ul> (axe "list", WCAG 1.3.1).
 */

require __DIR__ . '/../vendor/autoload.php';

$twig = new \Twig\Environment(
    new \Twig\Loader\FilesystemLoader(__DIR__ . '/../templates'),
    ['autoescape' => false, 'strict_variables' => false]
);

$pagination = [
    'current' => 2,
    'total' => 5,
    'prev' => ['link' => '/page/1/'],
    'next' => ['link' => '/page/3/'],
    'pages' => [
        ['title' => '1', 'link' => '/page/1/'],
        ['title' => '2', 'current' => true],
        ['title' => '3', 'link' => '/page/3/'],
        ['title' => '&hellip;'],
        ['title' => '5', 'link' => '/page/5/'],
    ],
];

$failures = [];

foreach (['middle page' => $pagination, 'first page' => ['prev' => null, 'current' => 1] + $pagination, 'last page' => ['next' => null, 'current' => 5] + $pagination] as $label => $data) {
    $html = $twig->render('partial/pagination.twig', ['pagination' => $data]);

    $document = new DOMDocument();
    $document->loadHTML('<!doctype html><meta charset="utf-8">' . $html, LIBXML_NOERROR);
    $xpath = new DOMXPath($document);

    foreach ($xpath->query('//ul/*[not(self::li)]') as $child) {
        $failures[] = sprintf('%s: <%s> is a direct child of the pagination list', $label, $child->nodeName);
    }

    $indicator = $xpath->query(sprintf('//ul/li[contains(normalize-space(.), "Pagina %d van 5")]', $data['current']));
    if ($indicator->length !== 1) {
        $failures[] = sprintf('%s: the page indicator is not exactly one list item', $label);
    }
}

if ($failures) {
    fwrite(STDERR, implode("\n", $failures) . "\n");
    exit(1);
}

echo "pagination markup OK\n";
