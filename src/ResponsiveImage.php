<?php

namespace Streekomroep;

final class ResponsiveImage
{
    public static function srcset($src, int $width, int $height): string
    {
        if ($width <= 0 || $height <= 0) {
            self::logInvalidDimensions($src, $width, $height);
            return '';
        }

        $widths = [
            max(192, (int) round($width / 2)),
            $width,
            $width * 2,
        ];
        $widths = array_unique($widths);
        sort($widths);

        $srcset = [];
        foreach ($widths as $srcsetWidth) {
            $srcsetHeight = (int) round($srcsetWidth / $width * $height);
            $srcset[] = \zw_imgproxy($src, $srcsetWidth, $srcsetHeight) . ' ' . $srcsetWidth . 'w';
        }

        return implode(', ', $srcset);
    }

    private static function logInvalidDimensions($src, int $width, int $height): void
    {
        static $warned = [];

        $type = get_debug_type($src);
        $key = $type . ':' . $width . 'x' . $height;
        if (isset($warned[$key])) {
            return;
        }

        $warned[$key] = true;
        error_log(sprintf(
            'responsive_image_srcset: invalid image dimensions (%dx%d) for source type %s; omitting srcset.',
            $width,
            $height,
            $type
        ));
    }
}
