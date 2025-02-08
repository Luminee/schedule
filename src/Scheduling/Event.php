<?php

namespace Luminee\Schedule\Scheduling;

use Illuminate\Console\Scheduling\Event as SchedulingEvent;
use Luminee\Schedule\Scheduling\Concerns\ManagesChains;

class Event extends SchedulingEvent
{
    use ManagesChains;

}
