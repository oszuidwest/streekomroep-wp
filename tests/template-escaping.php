<?php

/**
 * Regression coverage for explicit escaping in frontend templates.
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

function get_avatar_url(string $email, array $args = []): string
{
    return sprintf('https://example.test/avatar/%s?s=%d', rawurlencode($email), $args['size'] ?? 96);
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
$twig->addFilter(new \Twig\TwigFilter('imgproxy', fn ($src, $width, $height) => $src . '?w=' . $width . '&h=' . $height));

$failures = [];

$check = function (string $label, string $html, array $unescaped, array $escaped) use (&$failures) {
    foreach ($unescaped as $needle) {
        if (str_contains($html, $needle)) {
            $failures[] = sprintf('%s: output contains unescaped %s', $label, $needle);
        }
    }

    foreach ($escaped as $needle) {
        if (!str_contains($html, $needle)) {
            $failures[] = sprintf('%s: output is missing %s', $label, $needle);
        }
    }
};

// Substring matching cannot assert a bare hook: `data-volume` also matches `data-volume-control`.
// The JS contract is therefore checked against the parsed DOM, one element per attribute.
$check_hooks = function (string $label, string $html, array $attributes) use (&$failures) {
    $document = new DOMDocument();
    $document->loadHTML($html, LIBXML_NOERROR | LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    $xpath = new DOMXPath($document);

    foreach ($attributes as $attribute) {
        if ($xpath->query(sprintf('//*[@%s]', $attribute))->length === 0) {
            $failures[] = sprintf('%s: output has no element carrying %s', $label, $attribute);
        }
    }
};

$check_byline = function (string $label, string $html, array $names, int $avatar_count) use (&$failures) {
    $document = new DOMDocument();
    $document->loadHTML($html, LIBXML_NOERROR | LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    $xpath = new DOMXPath($document);

    $links = $xpath->query('//a');
    if ($links->length !== count($names)) {
        $failures[] = sprintf('%s: expected %d author links, got %d', $label, count($names), $links->length);
    }

    foreach ($names as $index => $name) {
        $link_text = $links->item($index) ? trim((string) preg_replace('/\s+/', ' ', $links->item($index)->textContent)) : '';

        if ($link_text !== $name) {
            $failures[] = sprintf('%s: author %d is missing or out of order', $label, $index + 1);
        }
    }

    $avatars = $xpath->query('//img');
    if ($avatars->length !== $avatar_count) {
        $failures[] = sprintf('%s: expected %d avatars, got %d', $label, $avatar_count, $avatars->length);
    }

    foreach ($avatars as $avatar) {
        if (!str_contains($avatar->getAttribute('src'), '?w=40&h=40')) {
            $failures[] = sprintf('%s: avatar does not request the expected source dimensions', $label);
        }

        if (!str_contains($avatar->getAttribute('class'), 'rounded-sm')) {
            $failures[] = sprintf('%s: avatar is missing the shared headshot treatment', $label);
        }
    }

    $linked_avatars = $xpath->query('//a/img');
    if ($linked_avatars->length !== $avatar_count) {
        $failures[] = sprintf('%s: every avatar should be grouped with its author link', $label);
    }

    $ampersands = $xpath->query('//span[normalize-space(.) = "&"]');
    $expected_ampersands = count($names) > 1 ? 1 : 0;
    if ($ampersands->length !== $expected_ampersands) {
        $failures[] = sprintf('%s: expected %d ampersand separators, got %d', $label, $expected_ampersands, $ampersands->length);
    }
};

$attribute_payload = '" onerror="alert(1)';
$text_payload = '<img src=x onerror=alert(1)>';
$script_payload = '<script>alert(2)</script>';

$byline_html = $twig->render('partial/byline.twig', [
    'post' => [
        'authors' => [
            [
                'name' => 'Alice & Bob',
                'link' => 'https://example.test/author/alice' . $attribute_payload,
                'avatar' => 'https://example.test/alice.jpg' . $attribute_payload,
            ],
            [
                'name' => 'Carol <script>',
                'link' => 'https://example.test/author/carol',
                'avatar' => null,
                'user_email' => 'carol@example.test',
            ],
            [
                'name' => 'Dave',
                'link' => 'https://example.test/author/dave',
                'avatar' => 'https://example.test/dave.jpg',
            ],
        ],
    ],
    'class' => 'mb-6' . $attribute_payload,
]);

$check(
    'byline.twig (multiple authors)',
    $byline_html,
    ['<script>', '" onerror="'],
    ['Alice &amp; Bob', 'Carol &lt;script&gt;', '&quot;&#x20;onerror&#x3D;&quot;']
);
$check_byline('byline.twig (multiple authors)', $byline_html, ['Alice & Bob', 'Carol <script>', 'Dave'], 3);

$single_author_byline = $twig->render('partial/byline.twig', [
    'post' => [
        'authors' => [
            [
                'name' => 'Enige auteur',
                'link' => 'https://example.test/author/enige-auteur',
                'avatar' => null,
            ],
        ],
    ],
]);
$check_byline('byline.twig (single author fallback)', $single_author_byline, ['Enige auteur'], 0);

$check(
    'fm-headshot.twig (attribute)',
    $twig->render('partial/fm-headshot.twig', [
        'photo' => [
            'src' => 'https://example.test/a.jpg' . $attribute_payload,
            'srcset' => 'https://example.test/b.jpg 2x' . $attribute_payload,
        ],
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

$fm_page_context = [
    'post' => ['id' => 1, 'class' => '', 'title' => 'Titel &amp; pagina'],
    'options' => ['radio_live_metadata_url' => 'wss://example.test/ws'],
    'broadcast' => $broadcast,
    'broadcast_data_url' => 'https://example.test/wp-json/zw/v1/broadcast_data',
    'schedule_refresh_after' => 30,
    'progress' => 50,
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
];

// `has_stream` swaps the now-playing sinks between static copy and broadcast data, so cover both branches.
$fm_page_html = [];
foreach (['stream' => [['url' => 'https://example.test/live.mp3', 'type' => 'audio/mpeg']], 'no stream' => []] as $label => $sources) {
    $fm_page_html[$label] = $twig->render('page-fm-player.twig', ['stream_sources' => $sources] + $fm_page_context);

    $check(
        sprintf('page-fm-player.twig (%s)', $label),
        $fm_page_html[$label],
        ['<img src=x', '<script>', '" onerror="', 'Titel &amp;amp; pagina'],
        ['&lt;img src=x', '&lt;script&gt;', 'Titel &amp; pagina']
    );
}

// setupVolume() in static/fm-live.js queries all five; they only render alongside a stream.
$check_hooks(
    'page-fm-player.twig (volume)',
    $fm_page_html['stream'],
    ['data-volume-control', 'data-volume-toggle', 'data-volume-panel', 'data-volume', 'data-volume-value']
);

if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo 'OK: template escaping holds for all payloads' . PHP_EOL;
