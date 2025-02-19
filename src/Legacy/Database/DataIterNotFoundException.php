<?php

namespace App\Legacy\Database;

use App\Exception\NotFoundException;

class DataIterNotFoundException extends NotFoundException
{
    public function __construct($id, DataModel $source = null)
    {
        parent::__construct(sprintf('%s with id %s was not found',
            $source
                ? substr((new \ReflectionClass($source))->getShortName(), strlen('DataModel'))
                : 'DataIter',
            $id));
    }
}
