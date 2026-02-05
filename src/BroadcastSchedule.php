<?php

namespace Streekomroep;

use Carbon\Carbon;
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

        $scheduleStart = new DateTime('now', wp_timezone());
        $scheduleStart->setTime(0, 0);
        $scheduleEnd = clone $scheduleStart;
        $scheduleEnd->add(new \DateInterval('P6D'));

        $tv_weeks = get_field('tv_week', 'option') ?: [];
        foreach ($tv_weeks as $week) {
            $start = DateTime::createFromFormat('Y-m-d', $week['tv_week_start'] ?? '', wp_timezone());
            if ($start === false) {
                continue;
            }
            $start->setTime(0, 0);
            if ($start < $scheduleStart) {
                $start = $scheduleStart;
            }
            $end = DateTime::createFromFormat('Y-m-d', $week['tv_week_eind'] ?? '', wp_timezone());
            if ($end === false) {
                continue;
            }
            $end->setTime(0, 0);
            if ($end > $scheduleEnd) {
                $end = $scheduleEnd;
            }

            $date = clone $start;
            while ($date <= $end) {
                $day = $this->getBroadcastDay($date);
                $dayname = $day->getName();

                foreach ($week['tv_week_shows'] as $entry) {
                    if ($entry['dag'] !== $dayname) {
                        continue;
                    }

                    if ($entry['show'] instanceof \WP_Post) {
                        $entry['show'] = Timber\Timber::get_post($entry['show']->ID);
                    }

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

        $date = clone $scheduleStart;
        while ($date <= $scheduleEnd) {
            $day = $this->getBroadcastDay($date); // Make sure day gets created
            $date->add(new \DateInterval('P1D'));
        }

        foreach ($this->days as $day) {
            $dayname = $day->getName();
            foreach ($shows as $show) {
                if (!$show->meta('fm_show_actief')) {
                    continue;
                }

                $rules = $show->meta('fm_show_programmatie');
                if (!$rules) {
                    continue;
                }

                foreach ($rules as $rule) {
                    if (!in_array($dayname, $rule['fm_show_dagen'])) {
                        continue;
                    }

                    $start = (new Carbon($day->date))->setTimeFromTimeString($rule['fm_show_starttijd']);
                    $end = (new Carbon($day->date))->setTimeFromTimeString($rule['fm_show_eindtijd']);
                    $day->add(new RadioBroadcast($show, $start, $end));
                }
            }
        }

        $fillerTitle = get_field('radio_geen_programma_naam', 'option');
        foreach ($this->days as $day) {
            $time = (new Carbon($day->date))->setTime(0, 0, 0);
            $newBroadcasts = [];
            foreach ($day->radio as $broadcast) {
                if ($broadcast->start != $time) {
                    $newBroadcasts[] = new RadioBroadcast($fillerTitle, $time, $broadcast->start);
                }
                $time = $broadcast->end;
            }

            if (!$time->isEndOfDay()) {
                $newBroadcasts[] = new RadioBroadcast($fillerTitle, $time, $time->copy()->endOfDay());
            }

            foreach ($newBroadcasts as $broadcast) {
                $day->add($broadcast);
            }
        }

        // Sort days by date
        ksort($this->days);

        // Remove days before today from schedule
        $today = new DateTime('now', wp_timezone());
        $today->setTime(0, 0);

        while (true) {
            $day = current($this->days);
            if ($day->date == $today) {
                break;
            }

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
                if ($returnNext) {
                    return $broadcast;
                }

                if ($broadcast == $current) {
                    $returnNext = true;
                }
            }
        }

        return null;
    }

    public function getCurrentRadioBroadcast()
    {
        $now = Carbon::now(wp_timezone());

        $today = $this->getToday();
        foreach ($today->radio as $broadcast) {
            if ($now->isBetween($broadcast->start, $broadcast->end)) {
                return $broadcast;
            }
        }

        return null;
    }

    public function getToday()
    {
        return $this->getBroadcastDay(new DateTime('now', wp_timezone()));
    }

    public function getTomorrow()
    {
        return $this->getBroadcastDay(new DateTime('tomorrow', wp_timezone()));
    }
}
