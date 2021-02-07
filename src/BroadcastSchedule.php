<?php

namespace Streekomroep;

class BroadcastSchedule
{
    /** @var BroadcastDay[] */
    public $days;

    public function __construct()
    {
        $shows = \Timber::get_posts([
            'post_type' => 'fm',
            'posts_per_page' => -1,
            'ignore_sticky_posts' => true,
        ]);
        $context['shows'] = $shows;

        $dayNames = ['maandag', 'dinsdag', 'woensdag', 'donderdag', 'vrijdag', 'zaterdag', 'zondag'];

        foreach ($dayNames as $day) {
            $this->days[$day] = new BroadcastDay($day);
        }

        foreach ($shows as $show) {
            if (!$show->meta('fm_show_actief'))
                continue;

            $rules = $show->meta('fm_show_programmatie');
            if ($rules) {
                foreach ($rules as $rule) {
                    foreach ($rule['fm_show_dagen'] as $day) {
                        $this->days[$day]->add(new Broadcast($show, $rule['fm_show_starttijd'], $rule['fm_show_eindtijd']));
                    }
                }
            }
        }

        $fillerTitle = get_field('radio_geen_programma_naam', 'option');
        foreach ($this->days as $day) {
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
    }
}
