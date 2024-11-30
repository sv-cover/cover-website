<?php

namespace App\Policy;

use App\Legacy\Database\DataIter;
use App\Legacy\Policy\AbstractPolicy;

class PolicyPartner extends AbstractPolicy
{
    public function userCanCreate(DataIter $partner): bool
    {
        return $this->identity->member_in_committee(COMMISSIE_BESTUUR)
            || $this->identity->member_in_committee(COMMISSIE_COMEXA);
    }

    public function userCanRead(DataIter $partner): bool
    {
        return True;
    }

    public function userCanUpdate(DataIter $partner): bool
    {
        return $this->identity->member_in_committee(COMMISSIE_BESTUUR)
            || $this->identity->member_in_committee(COMMISSIE_COMEXA);
    }

    public function userCanDelete(DataIter $partner): bool
    {
        return $this->userCanUpdate($partner);
    }
}
