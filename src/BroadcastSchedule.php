<?php

namespace Streekomroep;

use Cassandra\Date;
use DateTime;
use DateTimeImmutable;
use Timber;

class BroadcastSchedule
{
    /** @var BroadcastDay[] */
    public $days;

    public function __construct()
    {
        $this->days = [];

        foreach (get_field('tv_week', 'option') as $week) {
            $start = DateTime::createFromFormat('d/m/Y', $week['tv_week_start']);
            $start->setTime(0, 0);
            $end = DateTime::createFromFormat('d/m/Y', $week['tv_week_eind']);
            $end->setTime(0, 0);

            $date = clone $start;
            while ($date <= $end) {
                $day = $this->getBroadcastDay($date);
                $dayname = $day->getName();

                foreach ($week['tv_week_shows'] as $entry) {
                    if ($entry['dag'] !== $dayname) continue;
                    $day->add(new TelevisionBroadcast($entry['show'], $entry['naam_override'], $entry['starttijden']));
                }

                $date->add(new \DateInterval('P1D'));
            }
        }

        $shows = Timber::get_posts([
            'post_type' => 'fm',
            'posts_per_page' => -1,
            'ignore_sticky_posts' => true,
        ]);

        $start = new DateTime();
        $start->setTime(0, 0);
        $end = clone $start;
        $end->add(new \DateInterval('P6D'));

        $date = clone $start;
        while ($date <= $end) {
            $day = $this->getBroadcastDay($date); // Make sure day gets created
            $date->add(new \DateInterval('P1D'));
        }

        foreach ($this->days as $day) {
            $dayname = $day->getName();
            foreach ($shows as $show) {
                if (!$show->meta('fm_show_actief'))
                    continue;

                $rules = $show->meta('fm_show_programmatie');
                if (!$rules) continue;

                foreach ($rules as $rule) {
                    if (!in_array($dayname, $rule['fm_show_dagen'])) continue;
                    $day->add(new RadioBroadcast($show, $rule['fm_show_starttijd'], $rule['fm_show_eindtijd']));
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

        // Sort days by date
        ksort($this->days);

        // Remove days before today from schedule
        $today = new DateTime();
        $today->setTime(0, 0);

        while (true) {
            $day = current($this->days);
            if ($day->date == $today) break;

            array_shift($this->days);
        }

        // Only keep 7 days of data
        $this->days = array_slice($this->days, 0, 7);
    }

    private function getBroadcastDay(DateTime $date)
    {
        $format = $date->format('Y-m-d');
        if (!isset($this->days[$format])) {
            $this->days[$format] = new BroadcastDay(DateTimeImmutable::createFromMutable($date));
        }
        return $this->days[$format];
    }

    public function getNextRadioBroadcast()
    {
        $current = $this->getCurrentRadioBroadcast();
        $returnNext = false;

        foreach ($this->days as $day) {
            foreach ($day->radio as $broadcast) {
                if ($returnNext) return $broadcast;

                if ($broadcast == $current) {
                    $returnNext = true;
                }
            }
        }

        return null;
    }

    public function getCurrentRadioBroadcast()
    {
        $now = new DateTime();
        $hour = intval($now->format('G'));

        $today = $this->getToday();
        foreach ($today->radio as $broadcast) {
            $startHour = intval(substr($broadcast->startTime, 0, 2));
            $endHour = intval(substr($broadcast->endTime, 0, 2));
            if ($hour >= $startHour && $hour < $endHour) {
                return $broadcast;
            }
        }

        return null;
    }

    public function getToday()
    {
        return $this->getBroadcastDay(new DateTime());
    }

    public function getTomorrow()
    {
        return $this->getBroadcastDay(new DateTime('tomorrow'));
    }
}
