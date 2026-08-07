<?php

namespace Streekomroep;

use Carbon\Carbon;

class RadioBroadcast
{
    public $title;

    /** @var \Timber\Post */
    public $show;

    public Carbon $start;
    public Carbon $end;

    public function __construct($show, Carbon $start, Carbon $end)
    {
        if (is_string($show)) {
            $this->title = $show;
        } else {
            $this->show = $show;
        }

        $this->start = $start;
        $this->end = $end;
    }

    public static function sort(RadioBroadcast $lhs, RadioBroadcast $rhs)
    {
        return $lhs->start <=> $rhs->start;
    }

    public function getDayLabel(): ?string
    {
        if ($this->start->isToday()) {
            return null;
        }

        return $this->start->isTomorrow() ? 'morgen' : BroadcastDay::WEEKDAY_NAMES[$this->start->dayOfWeekIso];
    }

    public function getName()
    {
        if ($this->title) {
            return $this->title;
        }

        if (!$this->show) {
            throw new \RuntimeException('RadioBroadcast has no title or show set');
        }

        return $this->show->post_title;
    }

    /** Serializes the schedule contract shared by server and client renderers. */
    public function toArray(): array
    {
        return [
            'name' => $this->decode($this->getName()),
            'start' => $this->start->timestamp,
            'end' => $this->end->timestamp,
            'start_time' => $this->start->format('H:i'),
            'end_time' => $this->end->format('H:i'),
            'label' => $this->getDayLabel(),
            'show' => $this->show ? $this->serializeShow() : null,
        ];
    }

    private function serializeShow(): array
    {
        $makers = array_map(function (array $maker) {
            $photo = $maker['fm_show_maker_foto'] ?? null;

            return [
                'name' => $this->decode(trim((string) ($maker['fm_show_maker_naam'] ?? ''))),
                'photo' => $photo ? [
                    'src' => zw_imgproxy($photo, 44, 44),
                    'srcset' => zw_imgproxy($photo, 88, 88) . ' 2x',
                ] : null,
            ];
        }, zw_acf_rows($this->show->meta('fm_show_makers')));

        return [
            'title' => $this->decode($this->show->title()),
            'link' => $this->show->link(),
            'makers' => $makers,
            'makers_label' => $this->joinNames(array_column($makers, 'name')),
        ];
    }

    private function joinNames(array $names): string
    {
        $names = array_values(array_filter($names));
        $last = array_pop($names);

        return $names ? implode(', ', $names) . ' en ' . $last : (string) $last;
    }

    private function decode(string $text): string
    {
        return zw_plain_text($text);
    }
}
