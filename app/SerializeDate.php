<?php

namespace App;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use DateTimeInterface;

trait SerializeDate
{
    protected function serializeDate(DateTimeInterface $date): string {
        return $date instanceof DateTimeImmutable ?
            CarbonImmutable::instance($date)->toDateTimeString() :
            Carbon::instance($date)->toDateTimeString();
    }
}
