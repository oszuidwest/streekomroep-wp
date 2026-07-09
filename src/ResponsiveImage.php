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
        if ($width <= 0 || $height <= 0) {
            \wp_trigger_error(
                __METHOD__,
                sprintf(
                    'Invalid image dimensions (%dx%d) for source type %s; omitting srcset.',
                    $width,
                    $height,
                    get_debug_type($src)
                ),
                \E_USER_WARNING
            );
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
