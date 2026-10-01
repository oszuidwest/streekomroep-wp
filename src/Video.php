<?php

namespace Streekomroep;

use DateTimeImmutable;
use Exception;

class Video
{
    public const int STATUS_FINISHED = 4;
    private string $description = '';
    private ?DateTimeImmutable $broadcastDate = null;

    /**
     * Expects preprocessed _broadcastDate and _description properties.
     * Use VideoCollection::preprocessOne() before constructing.
     */
    public function __construct(private readonly BunnyCredentials $credentials, private readonly object $data)
    {
        if (isset($this->data->_description)) {
            $this->description = $this->data->_description;
        }

        if (isset($this->data->_broadcastDate) && $this->data->_broadcastDate !== null) {
            try {
                $this->broadcastDate = new DateTimeImmutable($this->data->_broadcastDate);
            } catch (Exception) {
                error_log('Failed to parse date for video with id: ' . $this->data->guid);
            }
        }
    }

    public function getId()
    {
        return $this->data->guid;
    }

    public function getThumbnail(): ?string
    {
        $thumbnailFileName = trim((string) ($this->data->thumbnailFileName ?? ''));
        if ($thumbnailFileName === '') {
            return null;
        }

        return $this->credentials->hostname . '/' . $this->data->guid . '/' . $thumbnailFileName;
    }

    public function getName()
    {
        return $this->data->title;
    }

    public function __get($name)
    {
        throw new Exception();
    }

    public function __isset($name)
    {
        return false;
    }

    public function isAvailable()
    {
        return $this->data->status === self::STATUS_FINISHED;
    }

    public function getBroadcastDate()
    {
        return $this->broadcastDate;
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function getDuration()
    {
        return $this->data->length;
    }

    public function getSources(): array
    {
        return [
            ['src' => $this->getMP4Url(), 'type' => 'video/mp4'],
            ['src' => $this->getPlaylistUrl(), 'type' => 'application/x-mpegURL'],
        ];
    }

    public function getPlaylistUrl()
    {
        return sprintf('%s/%s/playlist.m3u8', $this->credentials->hostname, $this->data->guid);
    }

    public function getMP4Url()
    {
        $allSizes = array_map(function ($size) {
            preg_match('/^(\d+)p$/', $size, $m);
            return intval($m[1] ?? 0);
        }, explode(',', (string) ($this->data->availableResolutions ?? '')));

        $sizes = array_filter($allSizes, fn ($size) => $size <= 720);

        if (empty($sizes)) {
            $sizes = $allSizes;
        }

        return sprintf('%s/%s/play_%dp.mp4', $this->credentials->hostname, $this->data->guid, max($sizes));
    }

    public function getAspectRatio(): float
    {
        return $this->data->width / $this->data->height;
    }
}
