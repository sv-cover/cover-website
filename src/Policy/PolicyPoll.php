<?php

namespace App\Policy;

use App\DataModel\DataModelCommissie;
use App\DataModel\DataModelPoll;
use App\Legacy\Authentication\Authentication;
use App\Legacy\Authentication\IdentityProviderInterface;
use App\Legacy\Database\DataIter;
use App\Legacy\Policy\PolicyInterface;

class PolicyPoll implements PolicyInterface
{
    protected IdentityProviderInterface $identity;

    public static function getSupportedModel(): string
    {
        return DataModelPoll::class;
    }

    public function __construct(
        protected Authentication $auth,
        protected DataModelPoll $pollModel,
    ) {
        $this->identity = $auth->getIdentity();
    }

    public function userCanCreate(DataIter $poll): bool
    {
        if (!$this->auth->loggedIn)
            return false;

        $current_poll = $this->pollModel->get_current();
        if (!$current_poll)
            return true;

        // Members always have to wait 14 days between creating polls
        if ($poll['committee_id'] === null && $this->identity->get('id') == $current_poll->member_id)
            return new \DateTime($current_poll['created_on']) < new \DateTime("-14 days");

        // If you didn't create the last poll, you'll have to wait untill it's closed or at least 7 days old
        return !$current_poll['is_open']
            || new \DateTime($current_poll['created_on']) < new \DateTime("-7 days");
    }

    public function userCanRead(DataIter $poll): bool
    {
        return true;
    }

    public function userCanUpdate(DataIter $poll): bool
    {
        return false;
    }

    public function userCanDelete(DataIter $poll): bool
    {
        if (!$this->auth->loggedIn)
            return false;

        // User owns it or board/acdcee
        return $this->identity->member_in_committee(DataModelCommissie::BOARD)
            || $this->identity->member_in_committee(DataModelCommissie::WEBCIE)
            || ($poll['committee_id'] !== null && $this->identity->member_in_committee($poll['committee_id']))
            || ($poll['committee_id'] === null && $this->identity->get('id') == $poll['member_id'])
        ;
    }

    public function userCanVote(DataIter $poll): bool
    {
        if (!$this->auth->loggedIn)
            return false;

        return $this->auth->loggedIn
            && $this->userCanRead($poll)
            && $poll['is_open']
            && !$poll->get_member_has_voted($this->identity->member())
        ;
    }

    public function userCanClose(DataIter $poll): bool
    {
        return $this->userCanDelete($poll);
    }

    public function userCanReopen(DataIter $poll): bool
    {
        return $this->identity->member_in_committee(DataModelCommissie::BOARD)
            || $this->identity->member_in_committee(DataModelCommissie::WEBCIE);
    }

    public function userCanLike(DataIter $poll): bool
    {
        return $this->auth->loggedIn;
    }
}
