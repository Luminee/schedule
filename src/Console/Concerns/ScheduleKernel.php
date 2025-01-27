<?php

namespace Luminee\Schedule\Console\Concerns;

use Luminee\Schedule\Scheduling\Schedule;

trait ScheduleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @return void
     */
    protected function defineConsoleSchedule()
    {
        parent::defineConsoleSchedule();

        $this->app->singleton(Schedule::class, function ($app) {
            return new Schedule;
        });

        $schedule = $this->app->make(Schedule::class);

        $this->scheduleChain($schedule);
    }

    /**
     * Define the application's command schedule.
     *
     * @param Schedule $schedule
     * @return void
     */
    protected function scheduleChain(Schedule $schedule)
    {
        //
    }
}