<?php

namespace App\Policy;

use App\Legacy\Database\DataIter;
use App\Legacy\Policy\AbstractPolicy;

class PolicyWiki extends AbstractPolicy
{
    public function userCanCreate(DataIter $wiki): bool
    {
        return false;
    }

    public function userCanRead(DataIter $wiki): bool
    {
        return true;
    }

    public function userCanUpdate(DataIter $wiki): bool
    {
        return false;
    }

    public function userCanDelete(DataIter $wiki): bool
    {
        return false;
    }
}
