<?php

namespace Streekomroep;


use Exception;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class Video
{
    private $data;
    private $yaml = false;

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
        if (in_array($name, ['id', 'thumbnail', 'name', 'link'])) {
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
            return $yaml['broadcast_date'];
        }

        try {
            return new \DateTime($yaml['broadcast_date']);
        } catch (Exception $e) {
            return null;
        }
    }

    private function getMeta()
    {
        if ($this->yaml !== false) {
            return $this->yaml;
        }

        if (!preg_match('/---\n(.*)$/m', $this->data->description, $m)) {
            $this->yaml = null;
            return $this->yaml;
        }

        try {
            $this->yaml = Yaml::parse($m[1], Yaml::PARSE_DATETIME);
        } catch (ParseException $e) {
            $this->yaml = null;
        }

        return $this->yaml;
    }

    public function getFolder()
    {
        if ($this->data->parent_folder === null) return null;

        return basename($this->data->parent_folder->uri);
    }
}
