<?php

namespace Luminee\Schedule\Scheduling\Concerns;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

trait ManageRecords
{
    public function setRecordUndo($record)
    {
        $record['status'] = 0;
        $this->setRecordStatus($record);
    }

    public function setRecordDoing($record)
    {
        $record['status'] = 1;
        $this->setRecordStatus($record);
    }

    public function setRecordDone($record, $hasRedo = false)
    {
        $record['status'] = 2;

        if (array_key_exists('has_redo', $record)) {
            $record['has_redo'] += $hasRedo ? 1 : 0;
        }

        $this->setRecordStatus($record);
    }

    public function setRecordFailed($record, $hasRedo = false)
    {
        $record['status'] = 9;

        if (array_key_exists('has_redo', $record)) {
            $record['has_redo'] += $hasRedo ? 1 : 0;
        }

        $this->setRecordStatus($record);
    }

    protected function setRecordStatus($record)
    {
        DB::table($this->table)->where('id', $record['id'])
            ->update(['status' => $record['status']]);
        Cache::set($record['key'], $record, 3600 * 24);
    }
}
