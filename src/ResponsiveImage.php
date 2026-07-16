<?php

namespace Streekomroep;

final class ResponsiveImage
{
    /**
     * Build imgproxy srcset candidates for the largest 1x CSS-pixel slot in the sizes attribute.
     *
     * @param \Timber\ImageInterface|string|null $src Image source accepted by zw_imgproxy().
     */
    public static function srcset($src, int $width, int $height): string
    {
        // Dimensions are validated by the calling macro in responsive-image.twig.
        if ($width <= 0 || $height <= 0) {
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
}
