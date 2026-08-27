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
        $widths = [
            max(192, (int) round($width / 2)),
            $width,
            $width * 2,
        ];

        return self::srcsetForWidths($src, $widths, $width, $height);
    }

    /**
     * Build imgproxy srcset candidates at the given widths, keeping the aspect
     * ratio implied by $width x $height.
     *
     * @param \Timber\ImageInterface|string|null $src Image source accepted by zw_imgproxy().
     * @param int[] $widths
     */
    public static function srcsetForWidths($src, array $widths, int $width, int $height): string
    {
        if ($width <= 0 || $height <= 0) {
            return '';
        }

        $widths = array_unique($widths);
        sort($widths);

        $srcset = [];
        foreach ($widths as $srcsetWidth) {
            $srcsetHeight = self::scaleHeight($srcsetWidth, $width, $height);
            $srcset[] = \zw_imgproxy($src, $srcsetWidth, $srcsetHeight) . ' ' . $srcsetWidth . 'w';
        }

        return implode(', ', $srcset);
    }

    /**
     * Height at $targetWidth for the aspect ratio implied by $width x $height.
     * Callers building a src attribute next to srcsetForWidths() must use this
     * too, so both produce the identical imgproxy URL for the shared width.
     */
    public static function scaleHeight(int $targetWidth, int $width, int $height): int
    {
        return (int) round($targetWidth / $width * $height);
    }
}
