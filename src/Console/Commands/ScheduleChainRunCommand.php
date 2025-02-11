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

        $allEvents = collect($this->schedule->events())->groupBy(function ($event) {
            return $event->getChain()->getName();
        });

        $chains = collect($this->schedule->chains());

        if ($chains->isEmpty()) {
            $this->components->info('No Chained scheduled tasks have been defined.');

            return;
        }

        foreach ($chains as $chain) {
            $this->handleChain($chain, $allEvents);

        }

        if (!$eventsRan) {
            $this->info('No scheduled commands are ready to run.');
        }
    }

    protected function handleChain($chain, $allEvents)
    {
        $this->line('<info>Running scheduled chain:</info> ' . $chain->getName());

        $chainRecord = $chain->getChainRecord();

        if ($chainRecord['status'] === 2) {
            return;
        }

        if ($chainRecord['status'] === 0) {
            $chain->setRecordDoing($chainRecord);
        }

        $events = $chain->getEvents($allEvents[$chain->getName()] ?? []);

        foreach ($events as $event) {
            if (!$event->filtersPass($this->laravel)) {
                continue;
            }

            $this->line('<info>Running scheduled command:</info> ' . $event->getSummaryForDisplay());

            $event->run($this->laravel);

            $eventsRan = true;
        }

        $chain->setRecordDone($chainRecord);
    }
}