<?php

namespace Streekomroep;

final readonly class BunnyVideoId
{
    public function __construct(
        public int $libraryId,
        public string $videoId
    ) {
    }
}
