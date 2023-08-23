<?php

namespace Streekomroep;

use Exception;
use League\CommonMark\Extension\FrontMatter\Data\SymfonyYamlFrontMatterParser;
use League\CommonMark\Extension\FrontMatter\Exception\InvalidFrontMatterException;
use League\CommonMark\Extension\FrontMatter\FrontMatterParser;

class Video
{
    /** @var BunnyVideo */
    private $data;
    private $yaml = [];
    private $description = '';
    private ?\DateTime $broadcastDate = null;
    private BunnyCredentials $credentials;


    public function __construct(BunnyCredentials $credentials, $data)
    {
        $this->credentials = $credentials;
        $this->data = $data;

        $description = null;
        foreach ($this->data->metaTags as $meta) {
            if ($meta->property === 'description') {
                $description = $meta->value;
            }
        }

        if (!$description) {
            return;
        }

        try {
            $frontMatterParser = new FrontMatterParser(new SymfonyYamlFrontMatterParser());
            $result = $frontMatterParser->parse($description);
            $this->yaml = $result->getFrontMatter();
            $this->description = $result->getContent();
        } catch (InvalidFrontMatterException $e) {
            $this->description = $description;
        }

        if (isset($this->yaml['broadcast_date'])) {
            $broadcast_date = $this->yaml['broadcast_date'];
            if (is_int($broadcast_date)) {
                // Re-parse broadcast date with the currently configured timezone
                $broadcast_date = date('Y-m-dTH:i:s', $broadcast_date);
            }

            try {
                $this->broadcastDate = new \DateTime($broadcast_date, wp_timezone());
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
        return "{$this->credentials->hostname}/{$this->data->guid}/{$this->data->thumbnailFileName}";
    }

    public function getName()
    {
        return $this->data->title;
    }

    public function getLink()
    {
        return 'https://iframe.mediadelivery.net/play/' . $this->data->videoLibraryId . '/' . $this->data->guid;
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
        return $this->data->status === BunnyVideo::STATUS_FINISHED;
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
        return sprintf("%s/%s/playlist.m3u8", $this->credentials->hostname, $this->data->guid);
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
        $sizes = array_filter($sizes, function($size) {
            return$size <= 720;
        });

        return sprintf("%s/%s/play_%dp.mp4", $this->credentials->hostname, $this->data->guid, max($sizes));
    }
}
