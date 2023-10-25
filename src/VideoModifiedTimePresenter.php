<?php

namespace Streekomroep;

class VideoModifiedTimePresenter extends \Yoast\WP\SEO\Presenters\Abstract_Indexable_Tag_Presenter
{
    public function __construct($date)
    {
        $this->date = $date;
    }

    protected $tag_format = '<meta property="article:modified_time" content="%s" />';

    public function get()
    {
        return $this->helpers->date->format($this->date);
    }
}
