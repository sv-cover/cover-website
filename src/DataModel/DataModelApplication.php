<?php

namespace App\DataModel;

use App\DataIter\DataIterApplication;
use App\Legacy\Database\DataModel;

class DataModelApplication extends DataModel
{
    public string $dataiter = DataIterApplication::class;
    public string $table = 'applications';
}
