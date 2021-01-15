<?php
/**
 * Template Name: FM Guide
 */

$context = Timber::context();

$timber_post = new Timber\Post();
$context['post'] = $timber_post;

$shows = Timber::get_posts([
    'post_type' => 'fm',
    'posts_per_page' => -1,
    'ignore_sticky_posts' => true,
]);
$context['shows'] = $shows;

class Day
{
    public $name;
    public $broadcasts = [];

    public function __construct($name)
    {
        $this->name = $name;
    }

    public function add(Broadcast $param)
    {
        $this->broadcasts[] = $param;
        usort($this->broadcasts, [Broadcast::class, 'sort']);
    }
}

class Broadcast
{
    public $show;
    public $startTime;
    public $endTime;

    public function __construct($show, $startTime, $endTime)
    {
        $this->show = $show;
        $this->startTime = $startTime;
        $this->endTime = $endTime;
    }

    public static function sort(Broadcast $lhs, Broadcast $rhs)
    {
        return strcmp($lhs->startTime, $rhs->startTime);
    }
}


$days = [];

foreach ($shows as $show) {
    $rules = $show->meta('fm_show_programmatie');
    if ($rules) {
        foreach ($rules as $rule) {
            foreach ($rule['fm_show_dagen'] as $day) {
                if (!isset($days[$day])) {
                    $days[$day] = new Day($day);
                }

                $days[$day]->add(new Broadcast($show, $rule['fm_show_starttijd'], $rule['fm_show_eindtijd']));
            }
        }
    }
}


$context['days'] = $days;

Timber::render(array('page-fm-guide.twig', 'page.twig'), $context);
