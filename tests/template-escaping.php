<?php

/**
 * Regression coverage for explicit escaping in frontend templates.
 *
 * Timber autoescaping is disabled; covered sinks must escape their own text.
 */

require __DIR__ . '/../vendor/autoload.php';

// Mirror WP's protocol allowlist so javascript: assertions exercise esc_url().
function esc_url(string $url): string
{
    $scheme = parse_url($url, PHP_URL_SCHEME);
    if ($scheme === false || (is_string($scheme) && !in_array(strtolower($scheme), ['http', 'https'], true))) {
        return '';
    }

    return htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
}

function get_post_type_archive_link(string $post_type): string
{
    return 'https://example.test/' . $post_type;
}

// VideoRenderer picks its markup by request context; tests flip $is_feed.
$is_feed = false;
function is_feed(): bool
{
    return $GLOBALS['is_feed'];
}

function is_admin(): bool
{
    return false;
}

function wp_doing_ajax(): bool
{
    return false;
}

function wp_is_serving_rest_request(): bool
{
    return false;
}

// The test only needs base.twig's content block.
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

$xpath_for = function (string $html): DOMXPath {
    $document = new DOMDocument();
    $document->loadHTML($html, LIBXML_NOERROR | LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    return new DOMXPath($document);
};

// DOM queries distinguish data-volume from data-volume-control.
$check_hooks = function (string $label, string $html, array $attributes) use ($xpath_for, &$failures) {
    $xpath = $xpath_for($html);

    foreach ($attributes as $attribute) {
        if ($xpath->query(sprintf('//*[@%s]', $attribute))->length === 0) {
            $failures[] = sprintf('%s: output has no element carrying %s', $label, $attribute);
        }
    }
};

$check_byline = function (string $label, string $html, array $names) use ($xpath_for, &$failures) {
    $xpath = $xpath_for($html);
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

    // The flex gap supplies the space after each attached comma.
    if (preg_match('/\s,/', $xpath->document->textContent)) {
        $failures[] = sprintf('%s: whitespace precedes a comma separator', $label);
    }
};

$attribute_payload = '" onerror="alert(1)';
$text_payload = '<img src=x onerror=alert(1)>';
$script_payload = '<script>alert(2)</script>';
$protocol_payload = 'javascript:alert(3)';

$check(
    'author.twig',
    $twig->render('author.twig', [
        'author' => [
            'name' => $text_payload,
            'description' => $script_payload,
            'avatar' => 'https://example.test/author.jpg' . $attribute_payload,
        ],
        'posts' => [],
    ]),
    [$text_payload, $script_payload, '" onerror="'],
    ['&lt;img', '&lt;script&gt;', '&quot; onerror=&quot;']
);

$check(
    'index.twig (title)',
    $twig->render('index.twig', ['title' => $text_payload, 'posts' => []]),
    ['<img src=x'],
    ['&lt;img src=x']
);

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
            ],
            [
                'name' => 'Dave',
                'link' => 'https://example.test/author/dave',
                'avatar' => $protocol_payload,
            ],
            [
                'name' => 'Eve',
                'link' => 'https://example.test/author/eve',
                'avatar' => null,
            ],
        ],
    ],
    'class' => 'mb-6' . $attribute_payload,
]);

$check(
    'byline.twig (multiple authors)',
    $byline_html,
    ['<script>', '" onerror="', 'javascript:'],
    [
        'Alice &amp; Bob',
        'Carol &lt;script&gt;',
        '&quot; onerror=&quot;',
        '&quot;&#x20;onerror&#x3D;&quot;',
    ]
);
$check_byline('byline.twig (multiple authors)', $byline_html, ['Alice & Bob', 'Carol <script>', 'Dave', 'Eve']);

$avatar_slots = $xpath_for($byline_html)->query('//span[@aria-hidden="true"]/*');
if (
    $avatar_slots->length !== 4
    || array_map(fn ($index) => $avatar_slots->item($index)->nodeName, range(0, 3)) !== ['img', 'span', 'img', 'span']
) {
    $failures[] = 'byline.twig (multiple authors): avatar slots do not preserve author order';
}

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
$check_byline('byline.twig (single author fallback)', $single_author_byline, ['Enige auteur']);

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

// Cover every broadcast sink and detect double encoding.
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

// Cover both now-playing branches selected by has_stream.
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

// setupVolume() expects all five controls when a stream exists.
$check_hooks(
    'page-fm-player.twig (volume)',
    $fm_page_html['stream'],
    ['data-volume-control', 'data-volume-toggle', 'data-volume-panel', 'data-volume', 'data-volume-value']
);

// Player URLs come from page and fragment settings; both attribute and protocol payloads must be neutralised.
$tv_post = fn (array $meta) => new class ($meta) {
    public string $title = 'Kanaal';

    public function __construct(private array $meta)
    {
    }

    public function meta(string $key): mixed
    {
        return $this->meta[$key] ?? null;
    }
};
$tv_meta = [
    'tv_webplayer_poster' => 'https://example.test/poster.jpg' . $attribute_payload,
    'tv_webplayer_hls_stream' => 'https://example.test/live.m3u8' . $attribute_payload,
];
$check(
    'page-tv-player.twig',
    $twig->render('page-tv-player.twig', ['tv_channels' => [], 'post' => $tv_post($tv_meta)]),
    ['" onerror="'],
    [
        'poster="https://example.test/poster.jpg',
        'data-live-src="https://example.test/live.m3u8',
        '<video slot="media" controls preload="none" playsinline poster="https://example.test/poster.jpg',
        ' src="https://example.test/live.m3u8',
    ]
);

// Without a usable stream URL the player gets no source at all, not an empty one.
foreach (['no stream' => null, 'unsafe stream' => $protocol_payload] as $label => $stream) {
    $check(
        sprintf('page-tv-player.twig (%s)', $label),
        $twig->render('page-tv-player.twig', ['tv_channels' => [], 'post' => $tv_post(['tv_webplayer_hls_stream' => $stream])]),
        ['data-live-src', 'javascript:', ' src="'],
        ['<hlsjs-video autoplay playsinline>', '<video slot="media" controls']
    );
}

$audio_fragment = new class ($protocol_payload) {
    public mixed $thumbnail = null;

    public function __construct(private string $payload)
    {
    }

    public function meta(string $key, array $options = []): mixed
    {
        return $key === 'fragment_url' ? $this->payload : null;
    }
};
$check(
    'partial/player-audio-fragment.twig',
    $twig->render('partial/player-audio-fragment.twig', ['fragment' => $audio_fragment, 'poster_url' => 'https://example.test/poster.jpg' . $attribute_payload]),
    ['javascript:', '" onerror="'],
    ['poster="https://example.test/poster.jpg', '<video src=""', ' controls autoplay']
);

$bunny_video = new \Streekomroep\Video(
    new \Streekomroep\BunnyCredentials(1, 'https://cdn.example.test', 'key'),
    (object) [
        'guid' => 'g',
        'width' => 16,
        'height' => 9,
        'thumbnailFileName' => 't.jpg',
        'availableResolutions' => '360p,720p,1080p',
    ]
);
$check(
    'VideoRenderer',
    \Streekomroep\VideoRenderer::renderPlayer($bunny_video, 'https://example.test/poster.jpg' . $attribute_payload),
    ['" onerror="'],
    [
        'poster="https://example.test/poster.jpg',
        '<hlsjs-video src="https://cdn.example.test/g/playlist.m3u8" playsinline><video slot="media" controls',
        'src="https://cdn.example.test/g/play_720p.mp4"',
    ]
);
$check(
    'VideoRenderer (thumbnail poster)',
    \Streekomroep\VideoRenderer::renderPlayer($bunny_video),
    [],
    ['poster="https://cdn.example.test/g/t.jpg"']
);

$is_feed = true;
$check(
    'VideoRenderer (feed)',
    \Streekomroep\VideoRenderer::renderPlayer($bunny_video),
    ['<hlsjs-video', '<video-player'],
    ['<video slot="media" controls preload="none" playsinline poster="https://cdn.example.test/g/t.jpg" src="https://cdn.example.test/g/play_720p.mp4"']
);
$is_feed = false;

if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo 'OK: template escaping holds for all payloads' . PHP_EOL;
