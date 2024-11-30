<?php

namespace App\Policy;

use App\Legacy\Database\DataIter;
use App\Legacy\Policy\PolicyInterface;
use App\Service\Authentication;

class PolicyBesturen implements PolicyInterface
{
    protected \IdentityProvider $identity;

    public static function getSupportedModel(): string
    {
        return \DataModelBesturen::class;
    }

    public function __construct(
        protected Authentication $auth,
    ) {
        $this->identity = $auth->getIdentity();
    }

    public function userCanCreate(DataIter $board): bool
    {
        return $this->identity->member_in_committee(COMMISSIE_BESTUUR);
    }

    public function userCanRead(DataIter $board): bool
    {
        return true;
    }

    public function userCanUpdate(DataIter $board): bool
    {
        return $this->identity->member_in_committee(COMMISSIE_BESTUUR);
    }

    public function userCanDelete(DataIter $board): bool
    {
        return $this->userCanUpdate($board);
    }
}
