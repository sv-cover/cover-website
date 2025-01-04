<?php

namespace App\Policy;

use App\DataModel\DataModelCommissie;
use App\DataModel\DataModelConfiguratie;
use App\Legacy\Authentication\Authentication;
use App\Legacy\Authentication\IdentityProviderInterface;
use App\Legacy\Database\DataIter;
use App\Legacy\Policy\PolicyInterface;

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
        return $this->identity->member_in_committee(DataModelCommissie::WEBCIE);
    }

    public function userCanRead(DataIter $entry): bool
    {
        return $this->identity->member_in_committee(DataModelCommissie::WEBCIE);
    }

    public function userCanUpdate(DataIter $entry): bool
    {
        return $this->identity->member_in_committee(DataModelCommissie::WEBCIE);
    }

    public function userCanDelete(DataIter $entry): bool
    {
        return $this->identity->member_in_committee(DataModelCommissie::WEBCIE);
    }
}
