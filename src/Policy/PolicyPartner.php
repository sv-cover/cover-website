<?php

namespace App\Policy;

use App\DataModel\DataModelPartner;
use App\Legacy\Authentication\IdentityProviderInterface;
use App\Legacy\Database\DataIter;
use App\Legacy\Policy\PolicyInterface;
use App\Service\Authentication;

class PolicyPartner implements PolicyInterface
{
    protected IdentityProviderInterface $identity;

    public static function getSupportedModel(): string
    {
        return DataModelPartner::class;
    }

    public function __construct(
        protected Authentication $auth,
    ) {
        $this->identity = $auth->getIdentity();
    }

    public function userCanCreate(DataIter $partner): bool
    {
        return $this->identity->member_in_committee(COMMISSIE_BESTUUR)
            || $this->identity->member_in_committee(COMMISSIE_COMEXA);
    }

    public function userCanRead(DataIter $partner): bool
    {
        return True;
    }

    public function userCanUpdate(DataIter $partner): bool
    {
        return $this->identity->member_in_committee(COMMISSIE_BESTUUR)
            || $this->identity->member_in_committee(COMMISSIE_COMEXA);
    }

    public function userCanDelete(DataIter $partner): bool
    {
        return $this->userCanUpdate($partner);
    }
}
