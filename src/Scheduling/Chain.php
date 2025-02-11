<?php

namespace Luminee\Schedule\Scheduling;

use Cron\CronExpression;
use Illuminate\Console\Scheduling\ManagesFrequencies;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Luminee\Schedule\Scheduling\Concerns\ManageRecords;

class Chain
{
    use ManagesFrequencies, ManageRecords;

    /**
     * @var string
     */
    protected $name;

    /**
     * The cron expression representing the event's frequency.
     *
     * @var string
     */
    public $expression = '* * * * * *';

    /**
     * The chain record table name
     *
     * @var string
     */
    protected $table = 'luminee_schedule_chain_record';

    public function __construct($name)
    {
        $this->name = $name;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEvents($events)
    {
        $names = [];
        foreach ($events as $event) {
            $names[] = $event->getEventName();
        }
        if (count(array_unique($names)) < $events->count()) {
            throw new \Exception('Chained event names must be unique.');
        }
        return $events;
    }

    /**
     * Determine if the given event should run based on the Cron expression.
     *
     * @return bool
     */
    public function isDue(): bool
    {
        return $this->expressionPasses();
    }

    /**
     * Determine if the Cron expression passes.
     *
     * @return bool
     */
    protected function expressionPasses(): bool
    {
        $date = Carbon::now();

        return $this->getCronExpression()->isDue($date->toDateTimeString());
    }

    protected function getCronExpression(): CronExpression
    {
        return CronExpression::factory($this->expression);
    }

    protected function getPeriod(): string
    {
        $date = Carbon::now();

        $cron = $this->getCronExpression();

        if ($cron->isDue($date->toDateTimeString())) {
            return $cron->getNextRunDate($date)->format('Y-m-d H:i');
        }

        return $cron->getPreviousRunDate($date)->format('Y-m-d H:i');
    }

    protected function getCacheKey($period): string
    {
        $key = str_replace(' ', '_', $this->name . '_' . $period);
        return 'schedule_chain_record_' . $key;
    }

    public function getChainRecord()
    {
        $period = $this->getPeriod();

        if ($record = Cache::get($key = $this->getCacheKey($period))) {
            return $record;
        }

        $record = DB::table($this->table)->where('name', $this->name)
            ->where('period', $period)->first();

        if (empty($record)) {
            DB::table($this->table)->insert($record = [
                'name' => $this->name,
                'period' => $period,
                'status' => 0
            ]);
            $record['id'] = DB::getPdo()->lastInsertId();
        } else {
            $record = (array)$record;
        }

        $record['key'] = $key;

        Cache::set($key, $record, 3600 * 24);

        return $record;
    }

}
