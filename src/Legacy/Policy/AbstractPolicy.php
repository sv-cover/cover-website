<?php

namespace App\Legacy\Policy;

use App\Legacy\Database\DataIter;
use App\Service\Authentication;
use App\Service\Database;

abstract class AbstractPolicy implements PolicyInterface
{
    protected $identity;

    public function __construct(
        protected Database $db,
        protected Authentication $auth,
    ) {
        $this->identity = $auth->getIdentity();
    }

    abstract public function userCanCreate(DataIter $iter): bool;

    abstract public function userCanRead(DataIter $iter): bool;

    abstract public function userCanUpdate(DataIter $iter): bool;

    abstract public function userCanDelete(DataIter $iter): bool;
}
