<?php

namespace Streekomroep;

use Carbon\Carbon;
use DateTime;
use DateTimeImmutable;
use Timber\Timber;

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
                        $entry['show'] = Timber::get_post($entry['show']->ID);
                    }

                    $day->addTelevision(new TelevisionBroadcast($entry['show'], $entry['naam_override'], $entry['starttijden']));
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
            // Ensure all seven days exist, including days without configured broadcasts.
            $day = $this->getBroadcastDay($date);
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
                    $day->addRadio(new RadioBroadcast($show, $start, $end));
                }
            }
        }

        $fillerTitle = get_field('radio_geen_programma_naam', 'option') ?: 'Non-stop';
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
                $day->addRadio($broadcast);
            }
        }

        // Normalize the schedule to today plus the next six calendar days.
        ksort($this->days);

        $today = new DateTime('now', wp_timezone());
        $today->setTime(0, 0);

        while (true) {
            $day = current($this->days);
            if ($day->date == $today) {
                break;
            }

            array_shift($this->days);
        }

        $this->days = array_slice($this->days, 0, 7);
    }

    private function getBroadcastDay(DateTime $date)
    {
        $format = $date->format('Y-m-d');
        $this->days[$format] ??= new BroadcastDay(DateTimeImmutable::createFromMutable($date));
        return $this->days[$format];
    }

    private function getRadioBroadcasts()
    {
        return array_merge(...array_column($this->days, 'radio'));
    }

    public function getNextRadioBroadcast(?RadioBroadcast $after = null)
    {
        $broadcasts = $this->getRadioBroadcasts();
        $index = array_search($after ?: $this->getCurrentRadioBroadcast(), $broadcasts, true);
        return $index === false ? null : ($broadcasts[$index + 1] ?? null);
    }

    /** Returns the broadcast immediately following this show's current or next slot. */
    public function getFollowingRadioBroadcast(int $showId)
    {
        $now = Carbon::now(wp_timezone());
        $broadcasts = $this->getRadioBroadcasts();
        $selected = null;

        foreach ($broadcasts as $broadcast) {
            if ($broadcast->show && $broadcast->show->ID == $showId) {
                $selected = $broadcast;
                if ($broadcast->end->isAfter($now)) {
                    break;
                }
            }
        }

        $index = array_search($selected, $broadcasts, true);
        return $index === false ? null : ($broadcasts[$index + 1] ?? null);
    }

    public function getCurrentRadioBroadcast()
    {
        $now = Carbon::now(wp_timezone());

        foreach ($this->getToday()->radio as $broadcast) {
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
