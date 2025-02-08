<?php

namespace Luminee\Schedule\Scheduling;

use Illuminate\Console\Scheduling\CallbackEvent as SchedulingCallbackEvent;
use Luminee\Schedule\Scheduling\Concerns\ManagesChains;

class CallbackEvent extends SchedulingCallbackEvent
{
    use ManagesChains;

}