<?php

namespace App\Legacy\Database;

class DatabaseLiteral
{
    protected $sql;

    public function __construct($sql)
    {
        $this->sql = $sql;
    }

    public function toSQL()
    {
        return $this->sql;
    }
}
