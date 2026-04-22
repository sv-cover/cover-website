<?php

namespace App\DataIter;

use App\Legacy\Database\DataIter;

class DataIterPollLike extends DataIter
{
    static public function fields()
    {
        return [
            'id',
            'poll_id',
            'member_id',
            'created_on',
        ];

    }
}
