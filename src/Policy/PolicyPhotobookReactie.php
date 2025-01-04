<?php

namespace App\Policy;

use App\DataModel\DataModelCommissie;
use App\DataModel\DataModelPhotobookReactie;
use App\Legacy\Authentication\IdentityProviderInterface;
use App\Legacy\Database\DataIter;
use App\Legacy\Policy\PolicyInterface;
use App\Service\Authentication;

class PolicyPhotobookReactie implements PolicyInterface
{
    protected IdentityProviderInterface $identity;

    public static function getSupportedModel(): string
    {
        return DataModelPhotobookReactie::class;
    }

    public function __construct(
        protected Authentication $auth,
    ) {
        $this->identity = $auth->getIdentity();
    }

    public function userCanCreate(DataIter $iter): bool
    {
        return $this->auth->loggedIn;
    }

    public function userCanRead(DataIter $iter): bool
    {
        return true;
    }

    public function userCanUpdate(DataIter $iter): bool
    {
        // PhotoCee and the authors of comments are the only one who can clean/update and delete comments.
        return $this->identity->member_in_committee(DataModelCommissie::PHOTOCEE)
            || $this->auth->loggedIn && $this->identity->get('id') == $iter->get('auteur');
    }

    public function userCanDelete(DataIter $iter): bool
    {
        return $this->userCanUpdate($iter);
    }

    public function userCanLike(DataIter $iter): bool
    {
        return $this->auth->loggedIn;
    }
}
