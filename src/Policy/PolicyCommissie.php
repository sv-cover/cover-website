<?php

namespace App\Policy;

use App\DataModel\DataModelCommissie;
use App\Legacy\Authentication\IdentityProviderInterface;
use App\Legacy\Database\DataIter;
use App\Legacy\Policy\PolicyInterface;
use App\Service\Authentication;

// Note that since working groups are just special committees, all
// these rules also apply to them!

class PolicyCommissie implements PolicyInterface
{
    protected IdentityProviderInterface $identity;

    public static function getSupportedModel(): string
    {
        return DataModelCommissie::class;
    }

    public function __construct(
        protected Authentication $auth,
    ) {
        $this->identity = $auth->getIdentity();
    }

    public function userCanCreate(DataIter $committee): bool
    {
        return $this->identity->member_in_committee(COMMISSIE_BESTUUR);
    }

    public function userCanRead(DataIter $committee): bool
    {
        return true;
    }

    public function userCanUpdate(DataIter $committee): bool
    {
        return $this->identity->member_in_committee(COMMISSIE_BESTUUR)
            || $this->identity->member_in_committee(COMMISSIE_KANDIBESTUUR);
    }

    public function userCanDelete(DataIter $committee): bool
    {
        return $this->identity->member_in_committee(COMMISSIE_BESTUUR);
    }
}
