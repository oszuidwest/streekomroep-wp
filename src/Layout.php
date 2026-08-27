<?php

namespace Streekomroep;

/**
 * The article content column as Tailwind renders it: max-w-3xl (768px) minus
 * md:px-12 (2 x 48px) from the md breakpoint up, and full width minus
 * px-6 (2 x 24px) below it.
 */
final class Layout
{
    public const int CONTENT_WIDTH = 672;

    public const string CONTENT_SIZES = '(min-width: 768px) ' . self::CONTENT_WIDTH . 'px, calc(100vw - 3rem)';
}
