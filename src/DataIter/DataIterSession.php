<?php

namespace App\DataIter;

use App\Legacy\Database\DataIter;

class DataIterSession extends DataIter
{
    static public function fields()
    {
        return [
            'session_id',
            'type',
            'member_id',
            'ip_address',
            'application',
            'created_on',
            'last_active_on',
            'timeout',
            'override_member_id',
            'override_committees',
            'device_enabled',
            'device_name',
        ];
    }
}
