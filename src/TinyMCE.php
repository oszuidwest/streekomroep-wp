<?php

namespace Streekomroep;

class TinyMCE
{
    public function __construct()
    {
        add_filter('tiny_mce_before_init', [$this, 'pasteAsText']);
    }

    public function pasteAsText(array $init): array
    {
        $init['paste_as_text'] = true;

        return $init;
    }
}
