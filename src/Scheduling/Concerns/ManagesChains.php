<?php

namespace Luminee\Schedule\Scheduling\Concerns;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Luminee\Schedule\Scheduling\Chain;

trait ManagesChains
{
    /**
     * @var Chain
     */
    protected $chain;

    /**
     * @var string
     */
    protected $eventName;

    /**
     * @var string[]
     */
    protected $dependsOn = [];

    /**
     * @var string[]
     */
    protected $leads = [];

    /**
     * @var bool
     */
    protected $blocked = false;

    /**
     * @var bool
     */
    protected $needRedo = false;

    /**
     * The chain record table name
     *
     * @var string
     */
    protected $table = 'luminee_schedule_chain_schedule_record';

    public function bindChain($chain): self
    {
        $this->chain = $chain;

        return $this;
    }

    public function getChain(): Chain
    {
        return $this->chain;
    }

    public function alias($name): self
    {
        $this->eventName = $name;

        return $this;
    }

    public function getEventName(): string
    {
        return $this->eventName;
    }

    public function dependsOn($schedule_list): self
    {
        $this->dependsOn = $schedule_list;

        return $this;
    }

    public function getDependsOn(): array
    {
        return $this->dependsOn;
    }

    public function leads($schedule_list): self
    {
        $this->leads = $schedule_list;

        return $this;
    }

    public function getLeads(): array
    {
        return $this->leads;
    }

    public function blocked(): self
    {
        $this->blocked = true;

        return $this;
    }

    public function isBlocked(): bool
    {
        return $this->blocked;
    }

    public function needRedo(): self
    {
        $this->needRedo = true;

        return $this;
    }

    public function isNeedRedo(): bool
    {
        return $this->needRedo;
    }

    protected function getRecordCacheKey($period, $eventName): string
    {
        $name = $this->getChain()->getName() . '_' . $period . '_' . $eventName;

        $key = str_replace(' ', '_', $name);

        return 'schedule_chain_schedule_record_' . $key;
    }

    public function getScheduleRecord($chainRecord, $eventName)
    {
        if ($record = Cache::get($key = $this->getRecordCacheKey($chainRecord['period'], $eventName))) {
            return $record;
        }

        $record = DB::table($this->table)->where('name', $eventName)
            ->where('chain_id', $chainRecord['id'])->first();

        if (empty($record)) {
            DB::table($this->table)->insert($record = [
                'name' => $eventName,
                'chain_id' => $chainRecord['id'],
                'status' => 0,
                'has_redo' => 0
            ]);
            $record['id'] = DB::getPdo()->lastInsertId();
        } else {
            $record = $record->toArray();
        }

        $record['key'] = $key;

        Cache::set($key, $record, 3600 * 24);

        return $record;
    }

    public function runDepends($chainRecord)
    {
        $depends = [];

        foreach ($this->dependsOn as $depend) {
            $record = $this->getScheduleRecord($chainRecord, $depend);

            if ($record['status'] === 2) {
                continue;
            }

            if ($record['status'] === 0) {
                $depends[] = $depend;
            }

            if ($record['status'] === 9) {
                if ($this->isBlocked()) {
                    return false;
                }

                $depends[] = $depend;
            }
        }

        return $depends;
    }

    public function runLeads($chainRecord): array
    {
        $leads = [];

        foreach ($this->leads as $lead) {
            $record = $this->getScheduleRecord($chainRecord, $lead);

            if ($record['status'] === 2 || $record['status'] === 9) {
                continue;
            }

            if ($record['status'] === 0) {
                $leads[] = $lead;
            }
        }

        return $leads;
    }


}
