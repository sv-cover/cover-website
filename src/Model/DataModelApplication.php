<?php
use App\Legacy\Database\DataIter;
use App\Legacy\Database\DataModel;

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

class DataModelApplication extends DataModel
{
    public $dataiter = 'DataIterApplication';

    public function __construct($db)
    {
        parent::__construct($db, 'applications');
    }
}
