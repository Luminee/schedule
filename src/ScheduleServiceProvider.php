<?php

namespace Luminee\Schedule;

use Illuminate\Support\ServiceProvider;
use Luminee\Schedule\Console\Commands\ScheduleChainRunCommand;
use Luminee\Schedule\Scheduling\Schedule;

class ScheduleServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton('luminee.schedule.command.schedule-chain:run', function ($app) {
            return new ScheduleChainRunCommand($this->app->make(Schedule::class));
        });

        $this->commands('luminee.schedule.command.schedule-chain:run');
    }
}