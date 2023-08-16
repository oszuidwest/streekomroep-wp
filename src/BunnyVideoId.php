<?php

namespace Streekomroep;

class BunnyVideoId
{
    public function __construct(
        public int $libraryId,
        public string $videoId,
    ) {
    }
}
