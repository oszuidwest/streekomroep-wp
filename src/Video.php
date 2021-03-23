<?php

namespace Streekomroep;


use Exception;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class Video
{
    private $data;
    private $yaml = false;
    private $description = '';
    private $didParseMeta = false;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function getId()
    {
        return basename($this->data->uri);
    }

    public function getThumbnail($width = 295)
    {
        foreach ($this->data->pictures->sizes as $size) {
            if ($size->width == $width) {
                return $size->link;
            }
        }

        throw new \Exception('Couldn\'t get desired width (' . $width . ')');
    }

    public function getLargestThumbnail()
    {
        $best = null;
        foreach ($this->data->pictures->sizes as $size) {
            if ($best === null) {
                $best = $size;
            } else if ($size->width > $best->width) {
                $best = $size;
            }
        }

        if ($best) {
            return $best;
        }

        throw new \Exception('Couldn\'t get thumbnail');
    }

    public function getName()
    {
        return $this->data->name;
    }

    public function getLink()
    {
        return $this->data->link;
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

    public function getBroadcastDate()
    {
        $yaml = $this->getMeta();
        if (!$yaml) return null;

        if (!isset($yaml['broadcast_date'])) {
            return null;
        }

        if ($yaml['broadcast_date'] instanceof \DateTime) {
            // Re-parse timestamp to interpret in WP-configured timezone
            return new \DateTime($yaml['broadcast_date']->format('Y-m-d H:i:s'), wp_timezone());
        }

        try {
            return new \DateTime($yaml['broadcast_date'], wp_timezone());
        } catch (Exception $e) {
            return null;
        }
    }

    private function getMeta()
    {
        $this->initMeta();
        return $this->yaml;
    }

    private function initMeta()
    {
        if ($this->didParseMeta) return;

        $this->didParseMeta = true;

        $desc = $this->data->description;
        $desc = preg_split('/^---\n/m', $desc);

        if (count($desc) == 1) {
            $this->yaml = null;
            $this->description = $desc[0];
            return;
        }


        $this->description = trim($desc[0]);

        try {
            $this->yaml = Yaml::parse($desc[1], Yaml::PARSE_DATETIME);
        } catch (ParseException $e) {
            $this->yaml = null;
        }
    }

    public function getFolder()
    {
        if ($this->data->parent_folder === null) return null;

        return basename($this->data->parent_folder->uri);
    }

    public function getDescription()
    {
        $this->initMeta();
        return $this->description;
    }
}
