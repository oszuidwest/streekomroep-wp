<?php

namespace Streekomroep;

final class Imgproxy
{
    /**
     * Picks the output format for an imgproxy URL.
     *
     * JPEG has no transparency or animation, so PNG, GIF and WebP sources get WebP, which supports both.
     * Whether animation survives depends on the imgproxy server's frame limit.
     * Everything else, including sources without an extension, stays JPEG.
     */
    public static function extension(string $src): string
    {
        $sourceExtension = strtolower(pathinfo((string) parse_url($src, PHP_URL_PATH), PATHINFO_EXTENSION));

        return in_array($sourceExtension, ['png', 'gif', 'webp'], true) ? 'webp' : 'jpeg';
    }
}
