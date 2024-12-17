<?php

namespace App\DataIter;

use App\Legacy\Database\DataIter;

class DataIterConfiguratie extends DataIter
{
    static public function fields()
    {
        return [
            'key',
            'value'
        ];
    }
}
