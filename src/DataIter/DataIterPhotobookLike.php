<?php

namespace App\DataIter;

use App\Legacy\Database\DataIter;

class DataIterPhotobookLike extends DataIter
{
    static public function fields()
    {
        return ['foto_id', 'lid_id', 'liked_on'];
    }
}
