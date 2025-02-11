<?php

namespace Luminee\Schedule\Scheduling\Concerns;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

trait ManageRecords
{
    public function setRecordDoing($record)
    {
        $record['status'] = 1;
        $this->setRecordStatus($record);
    }

    public function setRecordDone($record)
    {
        $record['status'] = 2;
        $this->setRecordStatus($record);
    }

    public function setRecordFailed($record)
    {
        $record['status'] = 9;
        $this->setRecordStatus($record);
    }

    protected function setRecordStatus($record)
    {
        DB::table($this->table)->where('id', $record['id'])
            ->update(['status' => $record['status']]);
        Cache::set($record['key'], $record, 3600 * 24);
    }
}
