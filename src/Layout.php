<?php

namespace Streekomroep;

/** Article geometry for the max-w-3xl px-6 md:px-12 container. */
final class Layout
{
    public const int CONTENT_WIDTH = 672;

    public const string CONTENT_SIZES = '(min-width: 768px) ' . self::CONTENT_WIDTH . 'px, calc(100vw - 3rem)';
}
