<?php
namespace App\Policy;

use App\Legacy\Database\DataIter;
use App\Legacy\Policy\AbstractPolicy;

class PolicyPhotobookFace extends AbstractPolicy
{
    public function userCanCreate(DataIter $iter): bool
    {
        return $this->auth->loggedIn;
    }

    public function userCanRead(DataIter $iter): bool
    {
        return $this->auth->loggedIn;
    }

    public function userCanUpdate(DataIter $iter): bool
    {
        return $this->auth->loggedIn;
    }

    public function userCanDelete(DataIter $iter): bool
    {
        return $this->auth->loggedIn;
    }
}
