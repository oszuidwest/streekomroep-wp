<?php

namespace Streekomroep;

use DateTimeImmutable;

class BroadcastDay
{
    public const WEEKDAY_NAMES = [
        1 => 'maandag',
        2 => 'dinsdag',
        3 => 'woensdag',
        4 => 'donderdag',
        5 => 'vrijdag',
        6 => 'zaterdag',
        7 => 'zondag'
    ];

    /** @var RadioBroadcast[] */
    public $radio = [];

    /** @var TelevisionBroadcast[] */
    public $television = [];

    /** @var DateTimeImmutable */
    public $date;

    public function __construct(DateTimeImmutable $date)
    {
        $this->date = $date;
    }

    public function getName()
    {
        return self::WEEKDAY_NAMES[(int)$this->date->format('N')];
    }

    public function addRadio(RadioBroadcast $broadcast)
    {
        $this->radio[] = $broadcast;
        usort($this->radio, [RadioBroadcast::class, 'sort']);
    }

    public function addTelevision(TelevisionBroadcast $broadcast)
    {
        $this->television[] = $broadcast;
    }
}
