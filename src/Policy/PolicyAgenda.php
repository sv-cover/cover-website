<?php

namespace App\Policy;

use App\Legacy\Database\DataIter;
use App\Legacy\Policy\AbstractPolicy;

class PolicyAgenda extends AbstractPolicy
{
    public function userCanCreate(DataIter $event): bool
    {
        // Anyone who is in a committee can create agenda items (for said committee)
        return $this->identity->member_in_committee();
    }

    public function userCanRead(DataIter $event): bool
    {
        // Only board, candidate board, and the creators of new agenda items can
        // read them when they are not yet confirmed by the board.
        if ($event->is_proposal())
            return $this->identity->member_in_committee(COMMISSIE_BESTUUR)
                || $this->identity->member_in_committee(COMMISSIE_KANDIBESTUUR)
                || $this->identity->member_in_committee($event->get('committee_id'));

        // Private agenda items can only be seen by people who could attend it
        if ($event['private'])
            return $this->identity->is_member()
                || $this->identity->is_donor()
                || $this->identity->is_device();

        // By default all agenda items are accessible
        return true;
    }

    public function userCanUpdate(DataIter $event): bool
    {
        // Proposals cannot be modified (but their original version can be!)
        if ($event->is_proposal())
            return false;

        // Board and candidate board can always update agenda items
        if ($this->identity->member_in_committee(COMMISSIE_BESTUUR) || $this->identity->member_in_committee(COMMISSIE_KANDIBESTUUR))
            return true;

        // And committee members may update their own agenda items of course
        if ($this->identity->member_in_committee($event->get('committee_id')))
            return true;

        return false;
    }

    public function userCanDelete(DataIter $event): bool
    {
        return $this->userCanUpdate($event);
    }

    public function userCanModerate(DataIter $event): bool
    {
        // Only proposals can be moderated
        if (!$event->is_proposal())
            return false;

        // And only board and candidate board may moderate
        return $this->identity->member_in_committee(COMMISSIE_BESTUUR)
            || $this->identity->member_in_committee(COMMISSIE_KANDIBESTUUR);
    }
}
