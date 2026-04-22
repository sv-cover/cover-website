<?php

namespace App\DataIter;

use App\Legacy\Database\DataIter;

class DataIterPollOption extends DataIter
{
    static public function fields()
    {
        return [
            'id',
            'poll_id',
            'option',
            'votes',
        ];
    }
}
