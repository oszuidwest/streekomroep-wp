<?php

namespace Streekomroep;

use Exception;
use League\CommonMark\ConverterInterface;
use League\CommonMark\Extension\FrontMatter\Output\RenderedContentWithFrontMatter;

class Video
{
    /** @var BunnyVideo */
    private $data;
    private $yaml = [];
    private $description = '';
    private ?\DateTime $broadcastDate = null;


    public function __construct($data, ConverterInterface $converter)
    {
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

        $result = $converter->convert($description);
        if ($result instanceof RenderedContentWithFrontMatter) {
            $this->yaml = $result->getFrontMatter();
        }

        $this->description = (string)$result;

        if (isset($this->yaml['broadcast_date'])) {
            $broadcast_date = $this->yaml['broadcast_date'];
            if (is_int($broadcast_date)) {
                // Re-parse broadcast date with the currently configured timezone
                $broadcast_date = date('Y-m-d H:i:s', $broadcast_date);
            }

            $this->broadcastDate = new \DateTime($broadcast_date, wp_timezone());
        }
    }

    public function getId()
    {
        return basename($this->data->guid);
    }

    public function getThumbnail()
    {
        $cdnHostname = get_field('bunny_cdn_hostname', 'option');
        return "{$cdnHostname}/{$this->data->guid}/{$this->data->thumbnailFileName}";
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
        return in_array($this->data->status, [BunnyVideo::STATUS_FINISHED, BunnyVideo::STATUS_RESOLUTION_FINISHED]);
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

    public function getFile()
    {
        return '';
    }
}
