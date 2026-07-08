<?php

namespace Streekomroep;

final class ResponsiveImage
{
    public static function srcset($src, $width, $height): string
    {
        $width = self::normalizeDimension($width);
        $height = self::normalizeDimension($height);

        if ($width <= 0 || $height <= 0) {
            return '';
        }

        $srcset = [];
        foreach (self::dimensions($width, $height) as $candidate) {
            $srcset[] = sprintf(
                '%s %s',
                \zw_imgproxy($src, $candidate['width'], $candidate['height']),
                $candidate['descriptor']
            );
        }

        return implode(', ', $srcset);
    }

    /**
     * @return array<int, array{width: int, height: int, descriptor: string}>
     */
    public static function dimensions(int $width, int $height): array
    {
        $srcset = [];

        foreach (self::widths($width) as $srcsetWidth) {
            $srcsetHeight = (int) round($srcsetWidth / $width * $height);
            $srcset[] = [
                'width' => $srcsetWidth,
                'height' => $srcsetHeight,
                'descriptor' => $srcsetWidth . 'w',
            ];
        }

        return $srcset;
    }

    /**
     * @return int[]
     */
    public static function widths(int $width): array
    {
        $widths = [
            max(192, (int) round($width / 2)),
            $width,
            $width * 2,
        ];

        $widths = array_values(array_unique(array_filter($widths)));
        sort($widths);

        return $widths;
    }

    private static function normalizeDimension($dimension): int
    {
        return max(0, (int) round((float) $dimension));
    }
}
