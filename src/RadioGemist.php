<?php

namespace Streekomroep;

use Carbon\CarbonImmutable;
use Timber\Timber;

/** Works out from the schedules which hourly recordings the audio logger should have. */
final class RadioGemist
{
    /**
     * Returns the latest broadcast day of the shows that were on air last, newest first.
     *
     * @return list<array{show: Post, times: list<CarbonImmutable>}>
     */
    public static function latest(int $limit): array
    {
        $shows = Timber::get_posts([
            'post_type' => 'fm',
            'post_status' => 'publish',
            // Single show pages hide the recordings behind the password.
            'has_password' => false,
            'nopaging' => true,
        ]);

        $broadcasts = [];
        foreach ($shows as $show) {
            $times = self::days($show->schedule())->current();
            if ($times) {
                $broadcasts[] = ['show' => $show, 'times' => $times];
            }
        }

        usort($broadcasts, fn ($lhs, $rhs) => end($rhs['times']) <=> end($lhs['times']));

        return array_slice($broadcasts, 0, $limit);
    }

    /**
     * Yields the recorded hours of each broadcast day, newest day first and hours in order.
     *
     * @param array $rules Complete rows from Post::schedule().
     *
     * @return \Generator<int, list<CarbonImmutable>>
     */
    public static function days(array $rules): \Generator
    {
        if (!$rules || !get_field('radio_gemist_actief', 'option')) {
            return;
        }

        $hour = CarbonImmutable::now('Europe/Amsterdam')->startOfHour()->subHour();
        $end = $hour->subDays((int) get_field('radio_gemist_retentie', 'option'));
        $times = [];

        // Subtract an absolute hour so DST transitions cannot stall the loop.
        for (; $hour > $end; $hour = $hour->subUTCHour()) {
            if ($times && !$hour->isSameDay($times[0])) {
                yield $times;
                $times = [];
            }

            if (self::airs($rules, $hour)) {
                array_unshift($times, $hour);
            }
        }

        if ($times) {
            yield $times;
        }
    }

    private static function airs(array $rules, CarbonImmutable $hour): bool
    {
        $day = BroadcastDay::WEEKDAY_NAMES[$hour->dayOfWeekIso];
        $time = $hour->format('H:i:s');

        return array_any($rules, fn ($rule) => in_array($day, $rule['fm_show_dagen'], true)
            && $rule['fm_show_starttijd'] <= $time
            && $time < $rule['fm_show_eindtijd']);
    }
}
