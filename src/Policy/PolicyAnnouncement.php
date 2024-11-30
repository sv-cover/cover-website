<?php

namespace App\Policy;

use App\Legacy\Database\DataIter;
use App\Legacy\Policy\PolicyInterface;
use App\Service\Authentication;

class PolicyAnnouncement implements PolicyInterface
{
    protected \IdentityProvider $identity;

    public static function getSupportedModel(): string
    {
        return \DataModelAnnouncement::class;
    }

    public function __construct(
        protected Authentication $auth,
    ) {
        $this->identity = $auth->getIdentity();
    }

    public function userCanCreate(DataIter $announcement): bool
    {
        if (isset($announcement['committee_id']))
            return $this->identity->member_in_committee($announcement['committee_id'])
                || $this->identity->member_in_committee(COMMISSIE_BESTUUR);

        return $this->identity->member_in_committee();
    }

    public function userCanRead(DataIter $announcement): bool
    {
        switch ($announcement['visibility'])
        {
            case \DataModelAnnouncement::VISIBILITY_PUBLIC:
                return true;

            case \DataModelAnnouncement::VISIBILITY_MEMBERS:
                return $this->identity->is_member() || $this->identity->is_donor();

            case \DataModelAnnouncement::VISIBILITY_ACTIVE_MEMBERS:
                return $this->identity->member_in_committee();

            default:
                return false;
        }
    }

    public function userCanUpdate(DataIter $announcement): bool
    {
        return $this->identity->member_in_committee(COMMISSIE_BESTUUR)
            || $this->identity->member_in_committee($announcement['committee_id']);
    }

    public function userCanDelete(DataIter $announcement): bool
    {
        return $this->userCanUpdate($announcement);
    }
}
