<?php

namespace Streekomroep;

use DateTimeImmutable;
use Exception;

class Video
{
    public const int STATUS_FINISHED = 4;
    private ?DateTimeImmutable $broadcastDate = null;

    /**
     * Expects preprocessed _broadcastDate and _description properties.
     * Use VideoCollection::preprocessOne() before constructing.
     */
    public function __construct(private readonly BunnyCredentials $credentials, private readonly object $data)
    {
        if (isset($this->data->_broadcastDate)) {
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
        return $this->data->_description ?? '';
    }

    public function getDuration()
    {
        return $this->data->length;
    }

    public function getSources(): array
    {
        $sources = [];
        $mp4 = $this->getMP4Url();
        if ($mp4) {
            $sources[] = ['src' => $mp4, 'type' => 'video/mp4'];
        }
        $sources[] = ['src' => $this->getPlaylistUrl(), 'type' => 'application/x-mpegURL'];

        return $sources;
    }

    public function getPlaylistUrl()
    {
        return sprintf('%s/%s/playlist.m3u8', $this->credentials->hostname, $this->data->guid);
    }

    /** Returns the largest MP4 rendition up to 720p, or null when Bunny lists none. */
    public function getMP4Url(): ?string
    {
        $resolutions = explode(',', (string) ($this->data->availableResolutions ?? ''));
        $allSizes = array_filter(array_map(intval(...), preg_grep('/^\d+p$/', $resolutions)));

        if (!$allSizes) {
            return null;
        }

        $sizes = array_filter($allSizes, fn ($size) => $size <= 720) ?: $allSizes;

        return sprintf('%s/%s/play_%dp.mp4', $this->credentials->hostname, $this->data->guid, max($sizes));
    }

    public function getAspectRatio(): float
    {
        return $this->data->width / $this->data->height;
    }
}
