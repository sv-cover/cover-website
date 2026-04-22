<?php

namespace App\DataIter;

use App\Legacy\Database\DataIter;

class DataIterSticker extends DataIter
{
    static public function fields()
    {
        return [
            'id',
            'label',
            'omschrijving',
            'lat',
            'lng',
            'toegevoegd_op',
            'toegevoegd_door',
            'foto'
        ];
    }

    public function get_member()
    {
        return $this['toegevoegd_door'] !== null
            ? $this->model->get_member_for_iter($this)
            : null;
    }
}
