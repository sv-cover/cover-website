<?php

namespace App\Policy;

use App\DataModel\DataModelConfiguratie;
use App\Legacy\Authentication\IdentityProviderInterface;
use App\Legacy\Database\DataIter;
use App\Legacy\Policy\PolicyInterface;
use App\Service\Authentication;

class PolicyConfiguratie implements PolicyInterface
{
    protected IdentityProviderInterface $identity;

    public static function getSupportedModel(): string
    {
        return DataModelConfiguratie::class;
    }

    public function __construct(
        protected Authentication $auth,
    ) {
        $this->identity = $auth->getIdentity();
    }

    public function userCanCreate(DataIter $entry): bool
    {
        return $this->identity->member_in_committee(COMMISSIE_EASY);
    }

    public function userCanRead(DataIter $entry): bool
    {
        return $this->identity->member_in_committee(COMMISSIE_EASY);
    }

    public function userCanUpdate(DataIter $entry): bool
    {
        return $this->identity->member_in_committee(COMMISSIE_EASY);
    }

    public function userCanDelete(DataIter $entry): bool
    {
        return $this->identity->member_in_committee(COMMISSIE_EASY);
    }
}
