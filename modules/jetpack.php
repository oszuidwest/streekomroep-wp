<?php

class Jetpack_Options
{
  public static function get_option_and_ensure_autoload()
  {
    return 'rectangular';
  }

  public static function get_option($option)
  {
    return get_option($option);
  }
}

class Jetpack
{
  public static function get_content_width()
  {
    return 672;
  }

  public static function get_active_modules()
  {
    return ['carousel'];
  }
}

function jetpack_photon_url($image_url, $args = array(), $scheme = null)
{
  return $image_url;
}
