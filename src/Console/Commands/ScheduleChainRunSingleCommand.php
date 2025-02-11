<?php

namespace Luminee\Schedule\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Luminee\Schedule\Scheduling\Chain;
use Luminee\Schedule\Scheduling\Schedule;

class ScheduleChainRunSingleCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'schedule-chain:run-single';

    /**
     * The console command signature.
     *
     * @var string
     */
    protected $signature = 'schedule-chain:run-single {chain : The chain name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run the scheduled single chain commands';

    /**
     * The schedule instance.
     *
     * @var Schedule
     */
    protected $schedule;

    /**
     * @var array
     */
    protected $chainRecord;

    /**
     * @var array
     */
    protected $namedEvents;

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
        $chain = $this->findChain();

        $inChain = "in chain [{$this->argument('chain')}]";

        if (empty($chain)) {
            $this->components->info("No Chain have been defined {$inChain}.");

            return;
        }

        $eventsRun = $this->handleChain($chain, collect($this->schedule->events()));

        if (!$eventsRun) {
            $this->info("No scheduled commands {$inChain} are ready to run.");
        }
    }

    protected function findChain()
    {
        $chainName = $this->argument('chain');

        $chains = collect($this->schedule->chains());

        foreach ($chains as $chain) {
            if ($chain->getName() === $chainName) {

                return $chain;
            }
        }
        return null;
    }

    protected function handleChain($chain, $events): bool
    {
        $this->line('<info>Running scheduled chain:</info> ' . $chain->getName());

        $this->chainRecord = $chainRecord = $chain->getChainRecord();

        if ($chainRecord['status'] === 2) {
            return false;
        }

        if ($chainRecord['status'] === 0) {
            $chain->setRecordDoing($chainRecord);
        }

        $eventsRun = false;

        $this->namedEvents = $events->keyBy(function ($event) {
            return $event->getEventName();
        });

        foreach ($events as $event) {
            $eventsRun = $this->handleSchedule($event) || $eventsRun;
        }

        $chain->setRecordDone($chainRecord);

        return $eventsRun;
    }

    protected function handleSchedule($event): bool
    {
        $chainRecord = $this->chainRecord;

        if ($event->getChain()->getName() !== $chainRecord['name']) {
            return false;
        }

        if (!$event->filtersPass($this->laravel)) {
            return false;
        }

        $record = $event->getScheduleRecord($chainRecord, $eventName = $event->getEventName());

        if ($record['status'] === 2) {
            return false;
        }

        if ($record['status'] === 0) {
            $event->setRecordDoing($record);
        }

        if (!$this->runDepends($event, $record)) {
            return false;
        }

        $this->line('<info>Running scheduled command:</info> ' . $eventName . ' ' . $event->getSummaryForDisplay());

        try {
            $event->run($this->laravel);

            $event->setRecordDone($record);

        } catch (Exception $e) {
            $this->error($e->getMessage());

            $event->setRecordFailed($record);

            if ($event->isBlocked()) {
                return false;
            }
        }

        $this->runLeads($event);

        return true;
    }

    protected function runDepends($event, $record): bool
    {
        if (($runDepends = $event->runDepends($this->chainRecord)) === false) {
            $event->setRecordFailed($record);

            $this->error('Run failed because of depends error.');

            return false;
        }


        foreach ($runDepends as $runDepend) {
            if (!isset($this->namedEvents[$runDepend])) {
                $event->setRecordFailed($record);

                $this->error('Run depend ' . $runDepend . ' not found.');

                return false;
            }

            $this->handleSchedule($this->namedEvents[$runDepend]);
        }

        return true;
    }

    protected function runLeads($event): bool
    {
        if (empty($runLeads = $event->runLeads($this->chainRecord))) {
            return true;
        }

        foreach ($runLeads as $runLead) {
            if (!isset($this->namedEvents[$runLead])) {
                $this->error('Run follow ' . $runLead . ' not found.');

                continue;
            }

            $this->handleSchedule($this->namedEvents[$runLead]);
        }

        return true;
    }
}