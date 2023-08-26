<?php

namespace Streekomroep;

class BunnyCredentials
{
    public int $libraryId;
    public string $hostname;
    public string $apiKey;

    public function __construct(
        int $libraryId,
        string $hostname,
        string $apiKey
    ) {
        $this->libraryId = $libraryId;
        $this->hostname = $hostname;
        $this->apiKey = $apiKey;
    }
}
