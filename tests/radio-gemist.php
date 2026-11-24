<?php

/**
 * Tests which recorded hours of radio shows are offered.
 */

require __DIR__ . '/../vendor/autoload.php';

use Carbon\Carbon;
use Streekomroep\RadioGemist;

function get_field(string $name, string $post_id): mixed
{
    return $GLOBALS['settings'][$name];
}

$rule = fn (array $days, string $start, string $end): array => ['fm_show_dagen' => $days, 'fm_show_starttijd' => $start . ':00', 'fm_show_eindtijd' => $end . ':00'];
$weekdays = [$rule(['maandag', 'dinsdag'], '07:00', '09:00'), $rule(['maandag'], '08:00', '10:00')];
$sunday = [$rule(['zondag'], '01:00', '04:00')];

// Each case holds the time, whether recordings are on, the schedule and the expected days and hours.
$cases = [
    'newest day first, hours in order and once, without the running hour or hours past the retention' => ['2026-11-24 08:30', true, $weekdays, [['24 07:00'], ['23 07:00', '23 08:00', '23 09:00'], ['17 08:00']]],
    'recordings turned off' => ['2026-11-24 12:00', false, $weekdays, []],
    'the hour that is skipped in spring' => ['2026-03-29 06:00', true, $sunday, [['29 01:00', '29 03:00']]],
    'the hour that repeats in autumn' => ['2026-10-25 06:00', true, $sunday, [['25 01:00', '25 02:00', '25 02:00', '25 03:00']]],
];

$failures = [];
foreach ($cases as $name => [$now, $active, $rules, $expected]) {
    Carbon::setTestNow(Carbon::parse($now, 'Europe/Amsterdam'));
    $settings = ['radio_gemist_actief' => $active, 'radio_gemist_retentie' => 7];

    $days = array_map(fn ($times) => array_map(fn ($time) => $time->format('d H:i'), $times), iterator_to_array(RadioGemist::days($rules)));
    if ($days !== $expected) {
        $failures[] = sprintf('%s: expected %s, got %s', $name, json_encode($expected), json_encode($days));
    }
}

// Stands in for the post query, so latest() runs without WordPress.
class_alias(get_class(new class {
    public static function get_posts(array $query): array
    {
        if ($query['post_status'] !== 'publish' || $query['has_password'] !== false) {
            return [];
        }

        return array_map(fn ($rules) => new class ($rules) {
            public function __construct(private array $rules)
            {
            }

            public function schedule(): array
            {
                return $this->rules;
            }
        }, $GLOBALS['schedules']);
    }
}), 'Timber\Timber');

Carbon::setTestNow(Carbon::parse('2026-11-24 12:00', 'Europe/Amsterdam'));
$settings = ['radio_gemist_actief' => true, 'radio_gemist_retentie' => 7];
$schedules = [[$rule(['maandag'], '07:00', '09:00')], [], [$rule(['dinsdag'], '07:00', '09:00')], [$rule(['dinsdag'], '06:00', '10:00')]];
$latest = array_map(fn ($broadcast) => end($broadcast['times'])->format('d H:i'), RadioGemist::latest(2));
if ($latest !== ['24 09:00', '24 08:00']) {
    $failures[] = 'latest shows: got ' . json_encode($latest);
}

if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo 'OK: radio recordings follow the schedule, the retention and clock changes' . PHP_EOL;
