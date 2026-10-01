<?php

namespace Streekomroep;

use Yoast\WP\SEO\Presenters\Abstract_Indexable_Tag_Presenter;

class VideoModifiedTimePresenter extends Abstract_Indexable_Tag_Presenter
{
    public function __construct(private $date)
    {
    }

    protected $tag_format = '<meta property="article:modified_time" content="%s" />';

    #[\Override]
    public function get()
    {
        return $this->helpers->date->format($this->date);
    }
}
