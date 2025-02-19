<?php

namespace App\Policy;

use App\DataModel\DataModelBesturen;
use App\DataModel\DataModelCommissie;
use App\Legacy\Authentication\Authentication;
use App\Legacy\Authentication\IdentityProviderInterface;
use App\Legacy\Database\DataIter;
use App\Legacy\Policy\PolicyInterface;

class PolicyBesturen implements PolicyInterface
{
    protected IdentityProviderInterface $identity;

    public static function getSupportedModel(): string
    {
        return DataModelBesturen::class;
    }

    public function __construct(
        protected Authentication $auth,
    ) {
        $this->identity = $auth->getIdentity();
    }

    public function userCanCreate(DataIter $board): bool
    {
        return $this->identity->member_in_committee(DataModelCommissie::BOARD);
    }

    public function userCanRead(DataIter $board): bool
    {
        return true;
    }

    public function userCanUpdate(DataIter $board): bool
    {
        return $this->identity->member_in_committee(DataModelCommissie::BOARD);
    }

    public function userCanDelete(DataIter $board): bool
    {
        return $this->userCanUpdate($board);
    }
}
