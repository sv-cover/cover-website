<?php

namespace App\Policy;

use App\Legacy\Database\DataIter;
use App\Legacy\Policy\PolicyInterface;
use App\Service\Authentication;

class PolicyEditable implements PolicyInterface
{
    protected \IdentityProvider $identity;

    public static function getSupportedModel(): string
    {
        return \DataModelEditable::class;
    }

    public function __construct(
        protected Authentication $auth,
    ) {
        $this->identity = $auth->getIdentity();
    }

    public function userCanCreate(DataIter $editable): bool
    {
        return $this->identity->member_in_committee(COMMISSIE_BESTUUR)
            || $this->identity->member_in_committee(COMMISSIE_KANDIBESTUUR)
            || $this->identity->member_in_committee(COMMISSIE_EASY);
    }

    public function userCanRead(DataIter $editable): bool
    {
        return true;
    }

    public function userCanUpdate(DataIter $editable): bool
    {
        // TODO: maybe its time for a more advanced access level here than just
        // ownership. Because for example the editables that are used by
        // the committee pages should be editable by the committee members
        // (which works right now because the committees are the owner)
        // but pages such as study information could also be editable by members
        // of both the BookCee, StudCee, and other study-related groups?
        return $this->identity->member_in_committee($editable['committee_id'])
            || $this->identity->member_in_committee(COMMISSIE_BESTUUR)
            || $this->identity->member_in_committee(COMMISSIE_KANDIBESTUUR)
            || $this->identity->member_in_committee(COMMISSIE_EASY);
    }

    public function userCanDelete(DataIter $editable): bool
    {
        // (I don't trust the candidate board enough yet to give them destructive powers!)
        return $this->identity->member_in_committee(COMMISSIE_BESTUUR)
            || $this->identity->member_in_committee(COMMISSIE_EASY);
    }
}
