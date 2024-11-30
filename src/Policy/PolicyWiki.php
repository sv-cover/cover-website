<?php

namespace App\Policy;

use App\Legacy\Authentication\IdentityProviderInterface;
use App\Legacy\Database\DataIter;
use App\Legacy\Policy\PolicyInterface;
use App\Service\Authentication;

class PolicyWiki implements PolicyInterface
{
    public static function getSupportedModel(): string
    {
        return \DataModelWiki::class;
    }

    public function userCanCreate(DataIter $wiki): bool
    {
        return false;
    }

    public function userCanRead(DataIter $wiki): bool
    {
        return true;
    }

    public function userCanUpdate(DataIter $wiki): bool
    {
        return false;
    }

    public function userCanDelete(DataIter $wiki): bool
    {
        return false;
    }
}
