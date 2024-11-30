<?php

namespace App\Legacy\Policy;

use App\Legacy\Database\DataIter;

interface PolicyInterface
{
    public static function getSupportedModel(): string;

    public function userCanCreate(DataIter $iter): bool;

    public function userCanRead(DataIter $iter): bool;

    public function userCanUpdate(DataIter $iter): bool;

    public function userCanDelete(DataIter $iter): bool;
}
