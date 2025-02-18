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
     * @var array
     */
    protected $executedEvents = [];

    /**
     * @var bool
     */
    protected $chainBlocked = false;

    /**
     * @var bool
     */
    protected $eventsRun = false;

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

        $this->handleChain($chain, collect($this->schedule->events()));
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
        $this->chainRecord = $chainRecord = $chain->getChainRecord();

        if ($chainRecord['status'] === 1) {
            $this->outputChainStatus($chain, 'comment', 'running');

            return false;
        }

        if ($chainRecord['status'] === 2) {
            $this->outputChainStatus($chain, 'info', 'done');

            return false;
        }

        if ($chainRecord['status'] === 9) {
            $this->outputChainStatus($chain, 'error', 'failed');

            return false;
        }

        $this->line("\r\n<info>Running scheduled chain:</info> " . $chain->getName());

        if ($chainRecord['status'] === 0) {
            $chain->setRecordDoing($chainRecord);
        }

        $this->namedEvents = $events->keyBy(function ($event) {
            return $event->getEventName();
        });

        $this->chainBlocked = $this->eventsRun = false;

        $this->executedEvents = [];

        foreach ($events as $event) {
            $this->handleSchedule($event);
        }

        if ($this->chainBlocked) {
            $chain->setRecordFailed($chainRecord);
        } else {
            $chain->setRecordDone($chainRecord);
        }


        if (!$this->eventsRun) {
            $this->line("<question>Schedule chain</question> " . $chain->getName() .
                " <question>has no event success.</question>");
        }

        return $this->eventsRun;
    }

    protected function outputChainStatus($chain, $style, $status)
    {
        $this->line("<$style>Schedule chain</$style> " . $chain->getName() .
            " <$style>is already $status.</$style>");
    }

    protected function handleSchedule($event, $redo = false): bool
    {
        $chainRecord = $this->chainRecord;

        if ($event->getChain()->getName() !== $chainRecord['name']) {
            return false;
        }

        if (!$event->filtersPass($this->laravel)) {
            return false;
        }

        $record = $event->getScheduleRecord($chainRecord, $eventName = $event->getEventName());

        $hasRedo = false;

        if ($this->chainBlocked) {
            if (!in_array($eventName, $this->executedEvents)) {
                $this->outputStatus($eventName, $event, 'comment', 'Blocked');
            }

            return false;
        }

        if ($record['status'] === 2) {
            if (!$redo) {
                return true;
            }

            $hasRedo = true;

            $event->setRecordDoing($record);
        }

        if ($record['status'] === 9) {
            if (!$redo) {
                return !$event->isBlocked();
            }

            $hasRedo = true;

            $event->setRecordDoing($record);
        }

        if ($record['status'] === 0) {
            $event->setRecordDoing($record);
        }

        if (!$this->runDepends($event, $record)) {
            $event->setRecordUndo($record);

            $this->outputStatus($eventName, $event, 'comment', 'Blocked');

            return false;
        }

        try {
            $event->run($this->laravel);

            $event->setRecordDone($record, $hasRedo);

            $this->outputStatus($eventName, $event, 'info', 'Successfully');

            $this->eventsRun = true;

        } catch (Exception $e) {
            $this->error($e->getMessage());

            $event->setRecordFailed($record, $hasRedo);

            $this->outputStatus($eventName, $event, 'error', 'Failed');

            if ($event->isBlocked()) {
                $this->chainBlocked = true;
            }
        }

        $this->executedEvents[] = $eventName;

        if (!$this->runLeads($event)) {
            return false;
        }

        return !$this->chainBlocked;
    }

    protected function outputStatus($eventName, $event, $style, $status)
    {
        $styledStatus = " <$style>$status</$style>";

        $this->line("<$style>Run scheduled command:</$style> " .
            $eventName . ' ' . $event->getSummaryForDisplay() . $styledStatus);
    }

    protected function runDepends($event, $record): bool
    {
        $dependsRun = true;

        foreach ($event->getDependsOn() as $runDepend) {
            if (!isset($this->namedEvents[$runDepend])) {
                $event->setRecordFailed($record);

                $this->error('Run depend ' . $runDepend . ' not found.');

                return false;
            }

            $dependsRun = $this->handleSchedule(
                    $this->namedEvents[$runDepend],
                    $event->isNeedRedo()
                ) && $dependsRun;
        }

        return $dependsRun;
    }

    protected function runLeads($event): bool
    {
        $leadsRun = true;

        foreach ($event->getLeads() as $runLead) {
            if (!isset($this->namedEvents[$runLead])) {
                $this->error('Run follow ' . $runLead . ' not found.');

                return false;
            }

            $leadsRun = $this->handleSchedule(
                    $this->namedEvents[$runLead],
                    $event->isNeedRedo()
                ) && $leadsRun;
        }

        return true;
    }
}