<?php

namespace Streekomroep;

final readonly class BunnyCredentials
{
    public function __construct(
        public int $libraryId,
        public string $hostname,
        public string $apiKey
    ) {
    }
}
