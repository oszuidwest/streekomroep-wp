<?php

namespace Streekomroep;

class BunnyVideoId
{
    public int $libraryId;
    public string $videoId;

    public function __construct(
        int $libraryId,
        string $videoId
    ) {
        $this->videoId = $videoId;
        $this->libraryId = $libraryId;
    }
}
