<?php

namespace Streekomroep;

final class ResponsiveImage
{
    public static function srcset($src, int $width, int $height): string
    {
        if ($width <= 0 || $height <= 0) {
            return '';
        }

        $widths = [
            max(192, (int) round($width / 2)),
            $width,
            $width * 2,
        ];
        $widths = array_values(array_unique($widths));
        sort($widths);

        $srcset = [];
        foreach ($widths as $srcsetWidth) {
            $srcsetHeight = (int) round($srcsetWidth / $width * $height);
            $srcset[] = \zw_imgproxy($src, $srcsetWidth, $srcsetHeight) . ' ' . $srcsetWidth . 'w';
        }

        return implode(', ', $srcset);
    }
}
