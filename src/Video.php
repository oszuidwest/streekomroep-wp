<?php

namespace Streekomroep;

use DateTimeImmutable;
use Exception;

class Video
{
    public const STATUS_FINISHED = 4;

    private object $data;
    private string $description = '';
    private ?DateTimeImmutable $broadcastDate = null;
    private BunnyCredentials $credentials;

    /**
     * Expects preprocessed data with _broadcastDate and _description properties.
     * Use VideoCollection::preprocessOne() before constructing.
     */
    public function __construct(BunnyCredentials $credentials, object $data)
    {
        $this->credentials = $credentials;
        $this->data = $data;

        if (isset($this->data->_description)) {
            $this->description = $this->data->_description;
        }

        if (isset($this->data->_broadcastDate) && $this->data->_broadcastDate !== null) {
            try {
                $this->broadcastDate = new DateTimeImmutable($this->data->_broadcastDate);
            } catch (Exception $e) {
                error_log('Failed to parse date for video with id: ' . $this->data->guid);
            }
        }
    }

    public function getId()
    {
        return $this->data->guid;
    }

    public function getThumbnail()
    {
        return $this->credentials->hostname . '/' . $this->data->guid . '/' . $this->data->thumbnailFileName;
    }

    public function getName()
    {
        return $this->data->title;
    }

    public function getLink()
    {
        return 'https://player.mediadelivery.net/play/' . $this->data->videoLibraryId . '/' . $this->data->guid;
    }

    public function __get($name)
    {
        throw new Exception();
    }

    public function __isset($name)
    {
        if (in_array($name, ['id', 'thumbnail', 'name', 'link', 'description'])) {
            return false;
        }
        throw new Exception();
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

    public function getPlaylistUrl()
    {
        return sprintf('%s/%s/playlist.m3u8', $this->credentials->hostname, $this->data->guid);
    }

    public function getMP4Url()
    {
        $sizes = explode(',', $this->data->availableResolutions);

        // Convert all sizes to numbers
        $sizes = array_map(function ($size) {
            preg_match('/^(\d+)p$/', $size, $m);
            return intval($m[1]);
        }, $sizes);

        // Only keep sizes <= 720
        $sizes = array_filter($sizes, function ($size) {
            return $size <= 720;
        });

        return sprintf('%s/%s/play_%dp.mp4', $this->credentials->hostname, $this->data->guid, max($sizes));
    }

    public function getAspectRatio(): float
    {
        return $this->data->width / $this->data->height;
    }
}
