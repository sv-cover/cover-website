<?php

namespace App\Policy;

use App\Legacy\Database\DataIter;
use App\Legacy\Policy\AbstractPolicy;

// NB: this policy is currently only used for device sessions.
class PolicySession extends AbstractPolicy
{
    public function userCanCreate(DataIter $session): bool
    {
        return false;
    }

    public function userCanRead(DataIter $session): bool
    {
        // You can see your own sessions
        if ($session['member_id'] == $this->identity->get('id'))
            return true;

        // WebCie can see all sessions
        return $this->identity->member_in_committee(COMMISSIE_EASY);
    }

    public function userCanUpdate(DataIter $session): bool
    {
        // Only AC/DCee can update sessions, and only device sessions.
        return $session['type'] === 'device'
            && $this->identity->member_in_committee(COMMISSIE_EASY);
    }

    public function userCanDelete(DataIter $session): bool
    {
        return $this->userCanUpdate($session);
    }
}
