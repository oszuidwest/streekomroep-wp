<?php

namespace Streekomroep;

use Carbon\Carbon;
use DateTime;
use DateTimeImmutable;
use Timber\Timber;

class BroadcastSchedule
{
    /** Retry interval when no current slot supplies a refresh boundary. */
    private const REFRESH_FALLBACK = 30;

    /** Cache for the normalized tv_week option rows. */
    private const TV_SCHEDULE_CACHE = 'zw_tv_schedule';

    /** ACF stores every tv_week repeater subfield as an option under this prefix. */
    public const OPTION_PREFIX = 'options_tv_week';

    /** @var BroadcastDay[] */
    public $days;

    /** @var RadioBroadcast[]|null */
    private $radioBroadcasts = null;

    public function __construct()
    {
        $this->days = [];

        $scheduleStart = new DateTime('now', wp_timezone());
        $scheduleStart->setTime(0, 0);
        $scheduleEnd = clone $scheduleStart;
        $scheduleEnd->add(new \DateInterval('P6D'));

        $tvWeeks = self::getTvWeeks();

        // One primed lookup per show instead of an uncached get_post() per
        // schedule entry per day.
        $showIds = [];
        foreach ($tvWeeks as $week) {
            foreach ($week['shows'] as $entry) {
                if ($entry['show']) {
                    $showIds[$entry['show']] = true;
                }
            }
        }

        $tvShows = [];
        if ($showIds) {
            _prime_post_caches(array_keys($showIds), false, true);
            foreach (array_keys($showIds) as $showId) {
                $tvShows[$showId] = Timber::get_post($showId);
            }
        }

        foreach ($tvWeeks as $week) {
            $start = DateTime::createFromFormat('Y-m-d', $week['start'], wp_timezone());
            if ($start === false) {
                continue;
            }
            $start->setTime(0, 0);
            if ($start < $scheduleStart) {
                $start = $scheduleStart;
            }
            $end = DateTime::createFromFormat('Y-m-d', $week['eind'], wp_timezone());
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

                foreach ($week['shows'] as $entry) {
                    if ($entry['dag'] !== $dayname) {
                        continue;
                    }

                    $name = $entry['naam_override'];
                    $show = $entry['show'] ? ($tvShows[$entry['show']] ?? null) : null;

                    // Override-only rows are valid for generic schedule entries such as reruns.
                    if (!$show && $name === '') {
                        continue;
                    }

                    $day->addTelevision(new TelevisionBroadcast($show, $name, $entry['starttijden']));
                }

                $date->add(new \DateInterval('P1D'));
            }
        }

        // Request published posts explicitly because the payload is cached
        // publicly and must not include private shows visible to an editor.
        $shows = Timber::get_posts([
            'post_type' => 'fm',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'ignore_sticky_posts' => true,
        ]);

        $showRules = [];
        foreach ($shows as $show) {
            $rules = $show->schedule();
            if ($rules) {
                $showRules[] = [$show, $rules];
            }
        }

        $date = clone $scheduleStart;
        while ($date <= $scheduleEnd) {
            // Ensure all seven days exist, including days without configured broadcasts.
            $day = $this->getBroadcastDay($date);
            $date->add(new \DateInterval('P1D'));
        }

        foreach ($this->days as $day) {
            $dayname = $day->getName();
            foreach ($showRules as [$show, $rules]) {
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

    /**
     * Returns the tv_week option rows normalized to scalars.
     *
     * The nested ACF repeater stores every subfield as a separate option, so
     * reading it cold costs one query per subfield. The normalized form is
     * cached; the option hooks in functions.php invalidate it on every
     * tv_week write through invalidateCache(), the TTL is only a safety net.
     *
     * @return array{start: string, eind: string, shows: array{dag: string, show: ?int, naam_override: string, starttijden: string}[]}[]
     */
    private static function getTvWeeks(): array
    {
        $cached = get_transient(self::TV_SCHEDULE_CACHE);
        if (is_array($cached)) {
            return $cached;
        }

        $weeks = [];
        foreach (zw_acf_rows(get_field('tv_week', 'option')) as $week) {
            $start = $week['tv_week_start'] ?? null;
            $eind = $week['tv_week_eind'] ?? null;

            // A row without a date range can never contribute broadcasts.
            if (!is_string($start) || !is_string($eind)) {
                continue;
            }

            $shows = [];
            foreach (zw_acf_rows($week['tv_week_shows'] ?? null) as $entry) {
                $shows[] = [
                    'dag' => is_string($entry['dag'] ?? null) ? $entry['dag'] : '',
                    'show' => ($entry['show'] ?? null) instanceof \WP_Post ? $entry['show']->ID : null,
                    'naam_override' => is_string($entry['naam_override'] ?? null) ? trim($entry['naam_override']) : '',
                    'starttijden' => is_string($entry['starttijden'] ?? null) ? $entry['starttijden'] : '',
                ];
            }

            $weeks[] = ['start' => $start, 'eind' => $eind, 'shows' => $shows];
        }

        set_transient(self::TV_SCHEDULE_CACHE, $weeks, DAY_IN_SECONDS);
        return $weeks;
    }

    public static function invalidateCache(): void
    {
        delete_transient(self::TV_SCHEDULE_CACHE);
    }

    private function getBroadcastDay(DateTime $date)
    {
        $format = $date->format('Y-m-d');
        $this->days[$format] ??= new BroadcastDay(DateTimeImmutable::createFromMutable($date));
        return $this->days[$format];
    }

    private function getRadioBroadcasts()
    {
        return $this->radioBroadcasts ??= array_merge(...array_column($this->days, 'radio'));
    }

    public function getNextRadioBroadcast(?RadioBroadcast $after = null)
    {
        $broadcasts = $this->getRadioBroadcasts();
        $index = array_search($after ?: $this->getCurrentRadioBroadcast(), $broadcasts, true);
        return $index === false ? null : ($broadcasts[$index + 1] ?? null);
    }

    /**
     * Returns the next programmed broadcasts, skipping filler such as non-stop music.
     *
     * @return RadioBroadcast[]
     */
    public function getUpcomingRadioBroadcasts(int $limit)
    {
        $now = Carbon::now(wp_timezone());
        $upcoming = [];

        // Direct appends preserve JSON list semantics and stop work at the requested limit.
        foreach ($this->getRadioBroadcasts() as $broadcast) {
            if (count($upcoming) === $limit) {
                break;
            }

            if ($broadcast->show && $broadcast->start->isAfter($now)) {
                $upcoming[] = $broadcast;
            }
        }

        return $upcoming;
    }

    public static function refreshAfter(?int $endTimestamp): int
    {
        if (!$endTimestamp) {
            return self::REFRESH_FALLBACK;
        }

        return max(1, $endTimestamp - time());
    }

    /**
     * Uses the caller's slot so crossing a boundary between lookups cannot extend stale data
     * through the following slot.
     */
    public function getRefreshAfter(?RadioBroadcast $current): int
    {
        return self::refreshAfter($current?->end->getTimestamp());
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
