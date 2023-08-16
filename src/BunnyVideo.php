<?php

namespace Streekomroep;

class BunnyVideo
{
    const STATUS_FINISHED = 3;
    const STATUS_RESOLUTION_FINISHED = 4;

    public int $videoLibraryId;
    public string $guid;
    public string $title;
    public string $dateUploaded;
    public int $views;
    public bool $isPublic;
    public int $length;
    public int $status;
    public int $framerate;
    public int $rotation;
    public int $width;
    public int $height;
    public string $availableResolutions;
    public int $thumbnailCount;
    public int $encodeProgress;
    public int $storageSize;
    public array $captions;
    public bool $hasMP4Fallback;
    public string $collectionId;
    public string $thumbnailFileName;
    public int $averageWatchTime;
    public int $totalWatchTime;
    public string $category;
    public array $chapters;
    public array $moments;
    /** @var BunnyMeta[] */
    public array $metaTags;
    public array $transcodingMessages;
}
