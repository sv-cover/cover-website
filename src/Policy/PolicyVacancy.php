<?php

namespace App\Policy;

use App\Legacy\Database\DataIter;
use App\Legacy\Policy\AbstractPolicy;

class PolicyVacancy extends AbstractPolicy
{
    public function userCanCreate(DataIter $vacancy): bool
    {
        return $this->identity->member_in_committee(COMMISSIE_BESTUUR)
            || $this->identity->member_in_committee(COMMISSIE_COMEXA);
    }

    public function userCanRead(DataIter $vacancy): bool
    {
        return True;
    }

    public function userCanUpdate(DataIter $vacancy): bool
    {
        return $this->identity->member_in_committee(COMMISSIE_BESTUUR)
            || $this->identity->member_in_committee(COMMISSIE_COMEXA);
    }

    public function userCanDelete(DataIter $vacancy): bool
    {
        return $this->userCanUpdate($vacancy);
    }
}
