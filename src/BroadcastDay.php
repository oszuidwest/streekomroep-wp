<?php

namespace Streekomroep;

use DateTimeImmutable;
use InvalidArgumentException;

class BroadcastDay
{
    private static $WEEKDAY_NAMES = [
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
        return self::$WEEKDAY_NAMES[(int)$this->date->format('N')];
    }

    public function add($param)
    {
        if ($param instanceof RadioBroadcast) {
            $this->radio[] = $param;
            usort($this->radio, [RadioBroadcast::class, 'sort']);
        } else if ($param instanceof TelevisionBroadcast) {
            $this->television[] = $param;
        } else {
            throw new InvalidArgumentException('Broadcast was of  unexpected type ' . get_class($param));
        }
    }
}
