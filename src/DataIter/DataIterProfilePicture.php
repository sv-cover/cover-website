<?php

namespace App\DataIter;

use App\Legacy\Database\DataIter;
use App\Legacy\Database\DataIterNotFoundException;

class DataIterProfilePicture extends DataIter
{
    static public function fields()
    {
        return [
            'id',
            'member_id',
            'created_on',
            'reviewed',
        ];
    }

    public function get_member()
    {
        try {
            return $this->model->get_member_for_iter($this);
        } catch (DataIterNotFoundException $e) {
            return null;
        }
    }

    public function get_stream()
    {
        return $this->model->get_stream($this);
    }

    public function get_mtime()
    {
        $created_on = new \DateTime($this['created_on']);
        return $created_on->getTimestamp();
    }
}
