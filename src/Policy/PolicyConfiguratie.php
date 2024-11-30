<?php

namespace App\Policy;

use App\Legacy\Database\DataIter;
use App\Legacy\Policy\AbstractPolicy;

class PolicyConfiguratie extends AbstractPolicy
{
    public function userCanCreate(DataIter $entry): bool
    {
        return $this->identity->member_in_committee(COMMISSIE_EASY);
    }

    public function userCanRead(DataIter $entry): bool
    {
        return $this->identity->member_in_committee(COMMISSIE_EASY);
    }

    public function userCanUpdate(DataIter $entry): bool
    {
        return $this->identity->member_in_committee(COMMISSIE_EASY);
    }

    public function userCanDelete(DataIter $entry): bool
    {
        return $this->identity->member_in_committee(COMMISSIE_EASY);
    }
}
