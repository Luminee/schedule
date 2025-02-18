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
        $chains = collect($this->schedule->chains());

        if ($chains->isEmpty()) {
            $this->components->info('No Chained scheduled tasks have been defined.');

            return;
        }

        foreach ($chains as $chain) {
            $this->call("schedule-chain:run-single", ['chain' => $chain->getName()]);
        }
    }
}