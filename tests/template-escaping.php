<?php

/**
 * Regression coverage for explicit escaping in FM live-page templates.
 *
 * Timber autoescaping is disabled, so every covered sink must escape plain text itself.
 * Run with: composer test:templates
 */

require __DIR__ . '/../vendor/autoload.php';

// Fixed safe URLs need attribute escaping only; URL validation is outside this test.
function esc_url(string $url): string
{
    return htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
}

function get_post_type_archive_link(string $post_type): string
{
    return 'https://example.test/' . $post_type;
}

// base.twig pulls in the full site chrome; the page under test only needs its content block.
$loader = new \Twig\Loader\ChainLoader([
    new \Twig\Loader\ArrayLoader(['base.twig' => '{% block content %}{% endblock %}']),
    new \Twig\Loader\FilesystemLoader(__DIR__ . '/../templates'),
]);

// Matches Timber's production defaults: no autoescaping, lenient variables.
$twig = new \Twig\Environment($loader, ['autoescape' => false, 'strict_variables' => false]);
$twig->addFunction(new \Twig\TwigFunction('function', fn ($name, ...$args) => $name(...$args)));
$twig->addFunction(new \Twig\TwigFunction('icon', fn () => ''));
$twig->addFilter(new \Twig\TwigFilter('plain', fn (?string $text) => html_entity_decode((string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8')));

$failures = [];

$check = function (string $label, string $html, array $unescaped, array $escaped) use (&$failures) {
    foreach ($unescaped as $needle) {
        if (str_contains($html, $needle)) {
            $failures[] = sprintf('%s: output contains unescaped %s', $label, $needle);
        }
    }

    foreach ($escaped as $needle) {
        if (!str_contains($html, $needle)) {
            $failures[] = sprintf('%s: output is missing escaped %s', $label, $needle);
        }
    }
};

$attribute_payload = '" onerror="alert(1)';
$text_payload = '<img src=x onerror=alert(1)>';
$script_payload = '<script>alert(2)</script>';

$check(
    'fm-portrait.twig (attribute)',
    $twig->render('partial/fm-portrait.twig', [
        'name' => $attribute_payload,
        'photo' => ['src' => 'https://example.test/a.jpg', 'srcset' => 'https://example.test/b.jpg 2x'],
    ]),
    ['" onerror="'],
    []
);

$check(
    'fm-upcoming-card.twig (text)',
    $twig->render('partial/fm-upcoming-card.twig', [
        'card' => '',
        'muted' => '',
        'href' => 'https://example.test/show',
        'time' => '20:00',
        'label' => '',
        'title' => $text_payload,
        'names' => $script_payload,
        'photo' => null,
    ]),
    ['<img src=x', '<script>'],
    ['&lt;img src=x', '&lt;script&gt;']
);

// Populate every live-page broadcast sink; the encoded title also catches double encoding.
$show = [
    'title' => $text_payload,
    'link' => 'https://example.test/show',
    'makers' => [
        [
            'name' => $attribute_payload,
            'photo' => ['src' => 'https://example.test/a.jpg', 'srcset' => 'https://example.test/b.jpg 2x'],
        ],
    ],
    'makers_label' => $script_payload,
];
$broadcast = [
    'name' => $text_payload,
    'start' => 1000,
    'end' => 2000,
    'start_time' => '20:00',
    'end_time' => '22:00',
    'label' => null,
    'show' => $show,
];

$fm_page_html = $twig->render('page-fm-player.twig', [
    'post' => ['id' => 1, 'class' => '', 'title' => 'Titel &amp; pagina'],
    'options' => ['radio_live_metadata_url' => 'wss://example.test/ws'],
    'broadcast' => $broadcast,
    'broadcast_data_url' => 'https://example.test/wp-json/zw/v1/broadcast_data',
    'schedule_refresh_after' => 30,
    'progress' => 50,
    'stream_sources' => [['url' => 'https://example.test/live.mp3', 'type' => 'audio/mpeg']],
    'media_artwork' => [],
    'upcoming' => [$broadcast],
    'frequency_groups' => [
        [
            'badge' => 'FM',
            'title' => 'Via de ether',
            'unit' => 'FM',
            'channels' => [['value' => $text_payload, 'place' => $script_payload]],
        ],
    ],
]);

$check(
    'page-fm-player.twig (page)',
    $fm_page_html,
    ['<img src=x', '<script>', '" onerror="', 'Titel &amp;amp; pagina'],
    ['&lt;img src=x', '&lt;script&gt;', 'Titel &amp; pagina']
);

foreach (['data-volume-control', 'data-volume-toggle', 'data-volume-panel', 'data-volume', 'data-volume-value'] as $attribute) {
    if (!str_contains($fm_page_html, $attribute)) {
        $failures[] = sprintf('page-fm-player.twig (volume): output is missing %s', $attribute);
    }
}

if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo 'OK: FM template escaping holds for all payloads' . PHP_EOL;
