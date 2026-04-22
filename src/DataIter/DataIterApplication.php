<?php

namespace App\DataIter;

use App\Legacy\Database\DataIter;

class DataIterApplication extends DataIter
{
    static public function fields()
    {
        return [
            'key',
            'name',
            'secret',
            'is_admin'
        ];
    }
}
