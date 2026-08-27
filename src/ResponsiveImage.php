<?php

namespace Streekomroep;

final class ResponsiveImage
{
    /**
     * Builds imgproxy candidates around the supplied 1x slot.
     *
     * @param \Timber\ImageInterface|string|null $src Image source accepted by zw_imgproxy().
     * @param int                                $width Largest 1x slot width.
     * @param int                                $height Height at that width.
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
     * Builds imgproxy candidates at explicit widths and the supplied aspect ratio.
     *
     * @param \Timber\ImageInterface|string|null $src Image source accepted by zw_imgproxy().
     * @param int[]                              $widths Candidate widths.
     * @param int                                $width Source width for the aspect ratio.
     * @param int                                $height Source height for the aspect ratio.
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
     * Scales a height to the target width.
     *
     * Use with srcsetForWidths() to keep shared imgproxy URLs identical.
     */
    public static function scaleHeight(int $targetWidth, int $width, int $height): int
    {
        return (int) round($targetWidth / $width * $height);
    }
}
