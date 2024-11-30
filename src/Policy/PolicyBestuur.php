<?php

namespace App\Policy;

use App\Legacy\Database\DataIter;
use App\Legacy\Policy\AbstractPolicy;

// Note that since working groups are just special committees, all
// these rules also apply to them!

class PolicyBestuur extends AbstractPolicy
{
    public function userCanCreate(DataIter $committee): bool
    {
        return $this->identity->member_in_committee(COMMISSIE_BESTUUR);
    }

    public function userCanRead(DataIter $committee): bool
    {
        return true;
    }

    public function userCanUpdate(DataIter $committee): bool
    {
        return $this->identity->member_in_committee(COMMISSIE_BESTUUR)
            || $this->identity->member_in_committee(COMMISSIE_KANDIBESTUUR);
    }

    public function userCanDelete(DataIter $committee): bool
    {
        return $this->identity->member_in_committee(COMMISSIE_BESTUUR)
            || $this->identity->member_in_committee(COMMISSIE_KANDIBESTUUR);
    }
}
