<?php

namespace App\Policy;

use App\Legacy\Database\DataIter;
use App\Legacy\Policy\AbstractPolicy;

class PolicyPhoto extends AbstractPolicy
{
    public function userCanCreate(DataIter $iter): bool
    {
        // TODO SFY
        return get_policy($iter['scope'])->userCanUpdate($iter['scope']);
    }

    public function userCanRead(DataIter $iter): bool
    {
        return get_policy($iter['scope'])->userCanRead($iter['scope']);
    }

    public function userCanUpdate(DataIter $iter): bool
    {
        return get_policy($iter['scope'])->userCanUpdate($iter['scope']);
    }

    public function userCanDelete(DataIter $iter): bool
    {
        return get_policy($iter['scope'])->userCanUpdate($iter['scope']);
    }

    public function userCanDownload(DataIter $iter): bool
    {
        return $this->auth->loggedIn;
    }

    public function userCanLike(DataIter $iter): bool
    {
        return $this->auth->loggedIn;
    }

    public function userCanSetPrivacy(DataIter $iter): bool
    {
        return $this->auth->loggedIn;
    }
}
