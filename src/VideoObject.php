<?php

namespace Streekomroep;

class VideoObject extends \Yoast\WP\SEO\Generators\Schema\Abstract_Schema_Piece
{
    public $video;

    public function __construct(VideoData $video)
    {
        $this->video = $video;
    }

    public function generate()
    {
        $timespan = $this->video->duration;
        $hour = floor($timespan / (60 * 60));
        $min = floor($timespan / 60) % 60;
        $sec = $timespan % 60;

        return [
            '@type' => 'VideoObject',
            '@id' => $this->context->canonical . '#video',
            'isPartOf' => [
                '@id' => $this->context->main_schema_id
            ],
            'name' => $this->video->name,
            'description' => $this->video->description,
            'thumbnailUrl' => [
                $this->video->thumbnailUrl
            ],
            'uploadDate' => $this->video->uploadDate,
            'duration' => sprintf('PT%dH%dM%dS', $hour, $min, $sec),
            'isFamilyFriendly' => true,
            'inLanguage' => 'nl',
            'contentUrl' => $this->video->contentUrl,
        ];
    }

    public function is_needed()
    {
        return true;
    }
}
