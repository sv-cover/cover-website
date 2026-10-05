<?php

namespace App\DataIter;

use App\Legacy\Database\DataIter;

class DataIterPhotoSubmission extends DataIter
{
    static public function fields()
    {
        return [
            'id',
            'boek',
            'uploaded_by',
            'filepath',
            'beschrijving',
            'submitted_on',
            'status',
            'reviewed_by',
            'reviewed_on',
        ];
    }
}
