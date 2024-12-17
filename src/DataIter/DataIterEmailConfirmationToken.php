<?php

namespace App\DataIter;

use App\Legacy\Database\DataIter;

class DataIterEmailConfirmationToken extends DataIter
{
    static public function fields()
    {
        return [
            'key',
            'member_id',
            'email',
            'created_on'
        ];
    }

    public function get_member()
    {
        return $this->model->get_member_for_iter($this);
    }
}
