<?php

namespace App\Policy;

use App\Legacy\Database\DataIter;
use App\Legacy\Policy\PolicyInterface;
use App\Service\Authentication;
use App\Service\Database;

class PolicyProfilePicture implements PolicyInterface
{
    protected \IdentityProvider $identity;

    public static function getSupportedModel(): string
    {
        return \DataModelProfilePicture::class;
    }

    public function __construct(
        protected Authentication $auth,
        protected Database $db,
    ) {
        $this->identity = $auth->getIdentity();
    }

    public function userCanCreate(DataIter $iter): bool
    {
        if (!$this->auth->loggedIn)
            return false;

        return $iter['member_id'] == $this->identity->get('id')
            || $this->identity->member_in_committee(COMMISSIE_EASY);
    }

    public function userCanRead(DataIter $iter): bool
    {
        // You can see all your profile pictures
        if ($iter['member_id'] == $this->identity->get('id'))
            return true;

        // Admins always get to see profile pictures
        if ($this->identity->member_in_committee(COMMISSIE_BESTUUR)
            || $this->identity->member_in_committee(COMMISSIE_KANDIBESTUUR)
            || $this->identity->member_in_committee(COMMISSIE_EASY)
        )
            return true;

        // Everyone else has to obey your privacy settings. Only show photo if member still exists.
        return $iter['member']
            && !$this->db->getModel('DataModelMember')->is_private($iter['member'], 'foto');
    }

    public function userCanUpdate(DataIter $iter): bool
    {
        return false;
    }

    public function userCanDelete(DataIter $iter): bool
    {
        // Members can delete their own profile pictures
        if ($iter['member_id'] == $this->identity->get('id'))
            return true;

        return $this->identity->member_in_committee(COMMISSIE_BESTUUR)
            || $this->identity->member_in_committee(COMMISSIE_KANDIBESTUUR)
            || $this->identity->member_in_committee(COMMISSIE_EASY);
    }

    public function userCanReview(DataIter $iter): bool
    {
        // Only unreviewed items can be reviewed
        if ($iter['reviewed'])
            return false;

        return $this->identity->member_in_committee(COMMISSIE_BESTUUR)
            || $this->identity->member_in_committee(COMMISSIE_KANDIBESTUUR)
            || $this->identity->member_in_committee(COMMISSIE_EASY);
    }
}
