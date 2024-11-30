<?php

namespace App\Policy;

use App\Legacy\Database\DataIter;
use App\Legacy\Policy\AbstractPolicy;

class PolicyBesturen extends AbstractPolicy
{
    public function userCanCreate(DataIter $board): bool
    {
        return $this->identity->member_in_committee(COMMISSIE_BESTUUR);
    }

    public function userCanRead(DataIter $board): bool
    {
        return true;
    }

    public function userCanUpdate(DataIter $board): bool
    {
        return $this->identity->member_in_committee(COMMISSIE_BESTUUR);
    }

    public function userCanDelete(DataIter $board): bool
    {
        return $this->userCanUpdate($board);
    }
}
