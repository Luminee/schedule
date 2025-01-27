<?php

namespace Luminee\Schedule\Console\Commands;

use Illuminate\Console\Command;
use Luminee\Schedule\Scheduling\Schedule;

class ScheduleChainRunCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'schedule-chain:run';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run the scheduled chains commands';

    /**
     * The schedule instance.
     *
     * @var Schedule
     */
    protected $schedule;

    /**
     * Create a new command instance.
     *
     * @param Schedule $schedule
     * @return void
     */
    public function __construct(Schedule $schedule)
    {
        $this->schedule = $schedule;

        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $eventsRan = false;

        foreach ($this->schedule->dueEvents($this->laravel) as $event) {
            if (!$event->filtersPass($this->laravel)) {
                continue;
            }

            $this->line('<info>Running scheduled command:</info> ' . $event->getSummaryForDisplay());

            $event->run($this->laravel);

            $eventsRan = true;
        }

        if (!$eventsRan) {
            $this->info('No scheduled commands are ready to run.');
        }
    }
}