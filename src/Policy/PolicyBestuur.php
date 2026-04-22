<?php

namespace App\Policy;

use App\DataModel\DataModelBestuur;
use App\DataModel\DataModelCommissie;
use App\Legacy\Authentication\Authentication;
use App\Legacy\Authentication\IdentityProviderInterface;
use App\Legacy\Database\DataIter;
use App\Legacy\Policy\PolicyInterface;

// Note that since working groups are just special committees, all
// these rules also apply to them!

class PolicyBestuur implements PolicyInterface
{
    protected IdentityProviderInterface $identity;

    public static function getSupportedModel(): string
    {
        return DataModelBestuur::class;
    }

    public function __construct(
        protected Authentication $auth,
    ) {
        $this->identity = $auth->getIdentity();
    }

    public function userCanCreate(DataIter $committee): bool
    {
        return $this->identity->member_in_committee(DataModelCommissie::BOARD);
    }

    public function userCanRead(DataIter $committee): bool
    {
        return true;
    }

    public function userCanUpdate(DataIter $committee): bool
    {
        return $this->identity->member_in_committee(DataModelCommissie::BOARD)
            || $this->identity->member_in_committee(DataModelCommissie::CANDY);
    }

    public function userCanDelete(DataIter $committee): bool
    {
        return $this->identity->member_in_committee(DataModelCommissie::BOARD)
            || $this->identity->member_in_committee(DataModelCommissie::CANDY);
    }
}
