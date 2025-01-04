<?php

namespace App\Policy;

use App\DataModel\DataModelCommissie;
use App\DataModel\DataModelVacancy;
use App\Legacy\Authentication\Authentication;
use App\Legacy\Authentication\IdentityProviderInterface;
use App\Legacy\Database\DataIter;
use App\Legacy\Policy\PolicyInterface;

class PolicyVacancy implements PolicyInterface
{
    protected IdentityProviderInterface $identity;

    public static function getSupportedModel(): string
    {
        return DataModelVacancy::class;
    }

    public function __construct(
        protected Authentication $auth,
    ) {
        $this->identity = $auth->getIdentity();
    }

    public function userCanCreate(DataIter $vacancy): bool
    {
        return $this->identity->member_in_committee(DataModelCommissie::BOARD)
            || $this->identity->member_in_committee(DataModelCommissie::COMEXA);
    }

    public function userCanRead(DataIter $vacancy): bool
    {
        return True;
    }

    public function userCanUpdate(DataIter $vacancy): bool
    {
        return $this->identity->member_in_committee(DataModelCommissie::BOARD)
            || $this->identity->member_in_committee(DataModelCommissie::COMEXA);
    }

    public function userCanDelete(DataIter $vacancy): bool
    {
        return $this->userCanUpdate($vacancy);
    }
}
