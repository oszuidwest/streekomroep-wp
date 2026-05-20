<?php

function zw_tinymce_paste_as_text(array $init): array
{
    $init['paste_as_text'] = true;

    return $init;
}

add_filter('tiny_mce_before_init', 'zw_tinymce_paste_as_text');
