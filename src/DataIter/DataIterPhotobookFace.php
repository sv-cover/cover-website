<?php

namespace App\DataIter;

use App\Legacy\Database\DataIter;

class DataIterPhotobookFace extends DataIter
{
    static public function fields()
    {
        return [
            'id',
            'foto_id',
            'x',
            'y',
            'w',
            'h',
            'lid_id',
            'deleted',
            'tagged_by',
            'tagged_on',
            'custom_label',
            'cluster_id',
        ];
    }

    public function get_photo()
    {
        return $this->model->get_photo_for_face($this);
    }

    public function get_lid()
    {
        return $this->model->get_member_for_face($this);
    }

    public function get_suggested_member()
    {
        return $this->model->get_suggested_member($this);
    }

    public function get_position()
    {
        return array(
            'x' => $this->get('x'),
            'y' => $this->get('y'),
            'w' => $this->get('w'),
            'h' => $this->get('h')
        );
    }
}
