<?php

namespace App\Policy;

use App\DataModel\DataModelCommissie;
use App\DataModel\DataModelPollComment;
use App\Legacy\Authentication\Authentication;
use App\Legacy\Authentication\IdentityProviderInterface;
use App\Legacy\Database\DataIter;
use App\Legacy\Policy\PolicyInterface;

class PolicyPollComment implements PolicyInterface
{
    protected IdentityProviderInterface $identity;

    public static function getSupportedModel(): string
    {
        return DataModelPollComment::class;
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
        return $this->identity->member_in_committee(DataModelCommissie::BOARD)
            || $this->identity->member_in_committee(DataModelCommissie::WEBCIE)
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
