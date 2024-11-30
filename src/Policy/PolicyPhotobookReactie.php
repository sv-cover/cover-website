<?php

namespace App\Policy;

use App\Legacy\Database\DataIter;
use App\Legacy\Policy\AbstractPolicy;

class PolicyPhotobookReactie extends AbstractPolicy
{
    public function userCanCreate(DataIter $iter): bool
    {
        return $this->auth->loggedIn;
    }

    public function userCanRead(DataIter $iter): bool
    {
        return true;
    }

    public function userCanUpdate(DataIter $iter): bool
    {
        // PhotoCee and the authors of comments are the only one who can clean/update and delete comments.

        return $this->identity->member_in_committee(COMMISSIE_FOTOCIE)
            || $this->auth->loggedIn && $this->identity->get('id') == $iter->get('auteur');
    }

    public function userCanDelete(DataIter $iter): bool
    {
        return $this->userCanUpdate($iter);
    }

    public function userCanLike(DataIter $iter): bool
    {
        return $this->auth->loggedIn;
    }
}
