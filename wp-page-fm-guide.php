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

    /** @var Broadcast[] */
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
        if (is_string($show)) {
            $this->title = $show;
        } else {
            $this->show = $show;
        }
        $this->startTime = $startTime;
        $this->endTime = $endTime;
    }

    public static function sort(Broadcast $lhs, Broadcast $rhs)
    {
        return strcmp($lhs->startTime, $rhs->startTime);
    }
}

$dayNames = ['maandag', 'dinsdag', 'woensdag', 'donderdag', 'vrijdag', 'zaterdag', 'zondag'];

/** @var Day[] $days */
$days = [];

foreach ($dayNames as $day) {
    $days[$day] = new Day($day);
}

foreach ($shows as $show) {
    if (!$show->meta('fm_show_actief'))
        continue;

    $rules = $show->meta('fm_show_programmatie');
    if ($rules) {
        foreach ($rules as $rule) {
            foreach ($rule['fm_show_dagen'] as $day) {
                $days[$day]->add(new Broadcast($show, $rule['fm_show_starttijd'], $rule['fm_show_eindtijd']));
            }
        }
    }
}

$fillerTitle = get_field('radio_geen_programma_naam', 'option');
foreach ($days as $day) {
    $time = '00:00:00';
    $newBroadcasts = [];
    foreach ($day->broadcasts as $broadcast) {
        if ($broadcast->startTime != $time) {
            $newBroadcasts[] = new Broadcast($fillerTitle, $time, $broadcast->startTime);
        }
        $time = $broadcast->endTime;
    }

    if ($time != '24:00:00') {
        $newBroadcasts[] = new Broadcast($fillerTitle, $time, '24:00:00');
    }

    foreach ($newBroadcasts as $broadcast) {
        $day->add($broadcast);
    }
}

$context['days'] = $days;

Timber::render(array('page-fm-guide.twig', 'page.twig'), $context);
