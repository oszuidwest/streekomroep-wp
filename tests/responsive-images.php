<?php

/**
 * Regression coverage for missing responsive-image sources.
 */

require __DIR__ . '/../vendor/autoload.php';

$loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/../templates');
$twig = new \Twig\Environment($loader, ['autoescape' => false, 'strict_variables' => false]);

$calls = [];
$twig->addFilter(new \Twig\TwigFilter('imgproxy', function ($src, $width, $height) use (&$calls) {
    $calls[] = 'imgproxy';
    return sprintf('%s?w=%d&h=%d', $src, $width, $height);
}));
$twig->addFunction(new \Twig\TwigFunction('responsive_image_srcset', function () use (&$calls) {
    $calls[] = 'srcset';
    return 'https://example.test/image.jpg 1x';
}));
$twig->addFunction(new \Twig\TwigFunction('responsive_image_srcset_widths', function () use (&$calls) {
    $calls[] = 'source-srcset';
    return 'https://example.test/image.jpg 1x';
}));

$template = $twig->createTemplate(<<<'TWIG'
{% import 'partial/responsive-image.twig' as responsive %}
{{ responsive.source(src, 320, 180, '100vw', '(min-width: 640px)') }}
{{ responsive.render(src, 320, 180, '100vw', 'Alt text', 'image') }}
TWIG);

$missing = trim($template->render(['src' => null]));
if ($missing !== '' || $calls !== []) {
    fwrite(STDERR, 'Missing image sources must not render markup or call imgproxy.' . PHP_EOL);
    exit(1);
}

$present = $template->render(['src' => 'https://example.test/image.jpg']);
if (
    !str_contains($present, '<source ')
    || !str_contains($present, '<img ')
    || $calls !== ['source-srcset', 'srcset', 'imgproxy']
) {
    fwrite(STDERR, 'Valid image sources must keep rendering responsive markup.' . PHP_EOL);
    exit(1);
}

echo 'OK: responsive images skip missing sources and render valid sources' . PHP_EOL;
