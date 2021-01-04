<?php

namespace Automattic\Jetpack;

class Assets
{
    public static function get_file_url_for_environment($min_path, $non_min_path, $package_path = '')
    {
        return get_stylesheet_directory_uri() . '/' . $non_min_path;
    }
}
