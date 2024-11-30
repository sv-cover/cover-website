<?php

namespace App\Policy;

use App\Legacy\Database\DataIter;
use App\Legacy\Policy\PolicyInterface;
use App\Service\Authentication;

class PolicyMailinglist implements PolicyInterface
{
    protected \IdentityProvider $identity;

    public static function getSupportedModel(): string
    {
        return \DataModelMailinglist::class;
    }

    public function __construct(
        protected Authentication $auth,
    ) {
        $this->identity = $auth->getIdentity();
    }

    public function userCanCreate(DataIter $iter): bool
    {
        // Only AC/DCee members can create a mailing list
        return $this->identity->member_in_committee(COMMISSIE_EASY);
        // return $this->identity->member_in_committee(COMMISSIE_BESTUUR)
        //  || $this->identity->member_in_committee(COMMISSIE_KANDIBESTUUR)
        //  || $this->identity->member_in_committee(COMMISSIE_EASY);
    }

    public function userCanRead(DataIter $iter): bool
    {
        return $this->identity->member_in_committee(COMMISSIE_BESTUUR)
            || $this->identity->member_in_committee(COMMISSIE_KANDIBESTUUR)
            || $this->identity->member_in_committee(COMMISSIE_EASY)
            || $this->identity->member_in_committee($iter['commissie']);
    }

    public function userCanUpdate(DataIter $iter): bool
    {
        return $this->userCanRead($iter);
    }

    public function userCanDelete(DataIter $iter): bool
    {
        return $this->userCanRead($iter);
    }

    public function userCanSubscribe(\DataIterMailinglist $list): bool
    {
        if (!$this->auth->loggedIn)
            return false;

        // You cannot subscribe yourself to a non-public list
        if (!$list['publiek'])
            return false;

        // You cannot subscribe to a list (or opt back in to an opt-out list) that doesn't accept your type
        if (!($list['has_members'] && $this->identity->is_member())
            && !($list['has_contributors'] && $this->identity->is_donor()))
            return false;

        // You cannot subscribe to a list that is targeted at a starting year that's not yours
        if (!empty($list['has_starting_year']) && $this->identity->get('beginjaar') != $list['has_starting_year'])
            return false;

        return true;
    }

    public function userCanUnsubscribe(\DataIterMailinglist $list): bool
    {
        // You cannot unsubscribe from non-public lists
        if (!$list['publiek'])
            return false;

        // Any other list is perfectly fine.
        return true;
    }

    public function userCanReadArchive(\DataIterMailinglist $list): bool
    {
        if (!$this->auth->loggedIn)
            return false;

        if ($list->bevat_lid($this->identity->member()))
            return true;

        if ($this->identity->member_in_committee($list['commissie']))
            return true;

        if ($this->identity->member_in_committee(COMMISSIE_EASY))
            return true;

        return false;
    }
}