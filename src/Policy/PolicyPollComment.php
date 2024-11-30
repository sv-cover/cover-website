<?php

namespace App\Policy;

use App\Legacy\Authentication\IdentityProviderInterface;
use App\Legacy\Database\DataIter;
use App\Legacy\Policy\PolicyInterface;
use App\Service\Authentication;

class PolicyPollComment implements PolicyInterface
{
    protected IdentityProviderInterface $identity;

    public static function getSupportedModel(): string
    {
        return \DataModelPollComment::class;
    }

    public function __construct(
        protected Authentication $auth,
    ) {
        $this->identity = $auth->getIdentity();
    }

    public function userCanCreate(DataIter $comment): bool
    {
        return $this->auth->loggedIn;
    }

    public function userCanRead(DataIter $comment): bool
    {
        return true;
    }

    public function userCanUpdate(DataIter $comment): bool
    {
        if (!$this->auth->loggedIn)
            return false;

        // User owns it or board/acdcee
        return $this->identity->member_in_committee(COMMISSIE_BESTUUR)
            || $this->identity->member_in_committee(COMMISSIE_EASY)
            || $this->identity->get('id') == $comment['member_id']
        ;
    }

    public function userCanDelete(DataIter $comment): bool
    {
        return $this->userCanUpdate($comment);
    }

    public function userCanLike(DataIter $comment): bool
    {
        return $this->auth->loggedIn;
    }
}
