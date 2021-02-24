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

        $dayNames = [
            1 => 'maandag',
            2 => 'dinsdag',
            3 => 'woensdag',
            4 => 'donderdag',
            5 => 'vrijdag',
            6 => 'zaterdag',
            7 => 'zondag'
        ];
        foreach ($dayNames as $no => $day) {
            $this->days[$day] = new BroadcastDay($no, $day);
        }

        foreach (get_field('programmatie', 'option') as $entry) {
            $times = explode(',', $entry['starttijden']);
            $times = array_map('trim', $times);
            $this->days[$entry['dag']]->add(new TelevisionBroadcast($entry['programma'], $entry['naam_override'], $times));
        }

        foreach ($shows as $show) {
            if (!$show->meta('fm_show_actief'))
                continue;

            $rules = $show->meta('fm_show_programmatie');
            if ($rules) {
                foreach ($rules as $rule) {
                    foreach ($rule['fm_show_dagen'] as $day) {
                        $this->days[$day]->add(new RadioBroadcast($show, $rule['fm_show_starttijd'], $rule['fm_show_eindtijd']));
                    }
                }
            }
        }

        $fillerTitle = get_field('radio_geen_programma_naam', 'option');
        foreach ($this->days as $day) {
            $time = '00:00:00';
            $newBroadcasts = [];
            foreach ($day->radio as $broadcast) {
                if ($broadcast->startTime != $time) {
                    $newBroadcasts[] = new RadioBroadcast($fillerTitle, $time, $broadcast->startTime);
                }
                $time = $broadcast->endTime;
            }

            if ($time != '24:00:00') {
                $newBroadcasts[] = new RadioBroadcast($fillerTitle, $time, '24:00:00');
            }

            foreach ($newBroadcasts as $broadcast) {
                $day->add($broadcast);
            }
        }


        // Re-sort days to start with today
        $now = new \DateTime();
        $weekday = (int)$now->format('N');

        reset($this->days);
        while (true) {
            $day = current($this->days);
            if ($day->weekday == $weekday) {
                break;
            }
            $day = array_shift($this->days);
            $this->days[] = $day;
        }
    }

    public function getNextBroadcast()
    {
        $current = $this->getCurrentBroadcast();
        $returnNext = false;

        // Loop twice, so we take in account current broadcasts at the end of the week
        for ($week = 0; $week < 2; $week++) {
            foreach ($this->days as $day) {
                foreach ($day->radio as $broadcast) {
                    if ($returnNext) return $broadcast;

                    if ($broadcast == $current) {
                        $returnNext = true;
                    }
                }
            }
        }

        return null;
    }

    public function getCurrentBroadcast()
    {
        $now = new \DateTime();
        $weekday = (int)$now->format('N');
        $hour = intval($now->format('G'));

        foreach ($this->days as $day) {
            if ($day->weekday != $weekday) continue;

            foreach ($day->radio as $broadcast) {
                $startHour = intval(substr($broadcast->startTime, 0, 2));
                $endHour = intval(substr($broadcast->endTime, 0, 2));

                if ($hour >= $startHour && $hour < $endHour) {
                    return $broadcast;
                }
            }
        }

        return null;
    }
}
