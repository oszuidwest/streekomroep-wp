<?php

/**
 * Regression test: the FM partials must escape broadcast text at the sink.
 *
 * The theme keeps broadcast data as canonical plain text (see zw_plain_text()), and Timber
 * runs Twig without autoescaping, so every template sink escapes explicitly. These renders
 * feed the partials the two payloads that would break out again if an escape is dropped.
 *
 * Run with: composer test:templates
 */

require __DIR__ . '/../vendor/autoload.php';

// The partials escape URLs through WordPress' esc_url via Twig's function(); outside
// WordPress a stub with equivalent escaping behaviour is enough to render them.
function esc_url(string $url): string
{
    return htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
}

// Matches Timber's production defaults: no autoescaping, lenient variables.
$twig = new \Twig\Environment(
    new \Twig\Loader\FilesystemLoader(__DIR__ . '/../templates'),
    ['autoescape' => false, 'strict_variables' => false]
);
$twig->addFunction(new \Twig\TwigFunction('function', fn ($name, ...$args) => $name(...$args)));

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

// Attribute context: a decoded quote must not break out of alt="...".
$check(
    'fm-portrait.twig (attribute)',
    $twig->render('partial/fm-portrait.twig', [
        'name' => '" onerror="alert(1)',
        'photo' => ['src' => 'https://example.test/a.jpg', 'srcset' => 'https://example.test/b.jpg 2x'],
    ]),
    ['" onerror="'],
    []
);

// Text context: decoded markup must render as text, not as elements.
$check(
    'fm-upcoming-card.twig (text)',
    $twig->render('partial/fm-upcoming-card.twig', [
        'card' => '',
        'muted' => '',
        'href' => 'https://example.test/show',
        'time' => '20:00',
        'label' => '',
        'title' => '<img src=x onerror=alert(1)>',
        'names' => '<script>alert(2)</script>',
        'photo' => null,
    ]),
    ['<img src=x', '<script>'],
    ['&lt;img src=x', '&lt;script&gt;']
);

if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo 'OK: FM template escaping holds for both payloads' . PHP_EOL;
