<?php

namespace App\DataIter;

use App\Legacy\Database\DataIter;

class DataIterBestuur extends DataIter
{
    static public function fields()
    {
        return [
            'id',
            'naam',
            'login',
            'page_id'
        ];
    }

    public function get_page()
    {
        return $this->model->get_page_for_iter($this);
    }
}
