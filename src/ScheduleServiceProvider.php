<?php

namespace Luminee\Schedule;

use Illuminate\Support\ServiceProvider;
use Luminee\Schedule\Console\Commands\ScheduleChainListCommand;
use Luminee\Schedule\Console\Commands\ScheduleChainRunCommand;
use Luminee\Schedule\Console\Commands\ScheduleChainRunSingleCommand;
use Luminee\Schedule\Scheduling\Schedule;

class ScheduleServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton('luminee.schedule.command.schedule-chain:run', function ($app) {
            return new ScheduleChainRunCommand($this->app->make(Schedule::class));
        });

        $this->app->singleton('luminee.schedule.command.schedule-chain:list', function ($app) {
            return new ScheduleChainListCommand($this->app->make(Schedule::class));
        });

        $this->app->singleton('luminee.schedule.command.schedule-chain:run-single', function ($app) {
            return new ScheduleChainRunSingleCommand($this->app->make(Schedule::class));
        });

        $this->commands('luminee.schedule.command.schedule-chain:run');

        $this->commands('luminee.schedule.command.schedule-chain:list');

        $this->commands('luminee.schedule.command.schedule-chain:run-single');
    }
}