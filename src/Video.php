<?php

namespace Streekomroep;

use Exception;
use League\CommonMark\ConverterInterface;
use League\CommonMark\Extension\FrontMatter\Data\SymfonyYamlFrontMatterParser;
use League\CommonMark\Extension\FrontMatter\Exception\InvalidFrontMatterException;
use League\CommonMark\Extension\FrontMatter\FrontMatterParser;
use League\CommonMark\Extension\FrontMatter\Output\RenderedContentWithFrontMatter;

class Video
{
    /** @var BunnyVideo */
    private $data;
    private $yaml = [];
    private $description = '';
    private ?\DateTime $broadcastDate = null;


    public function __construct($data)
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
        // TODO: inject hostname using constructor
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
        // TODO: return mp4 url
        return '';
    }
}
