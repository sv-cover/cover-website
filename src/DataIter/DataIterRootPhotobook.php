<?php

namespace App\DataIter;

use App\DataIter\DataIterPhotobook;
use App\Legacy\Database\DataIter;

class DataIterRootPhotobook extends DataIterPhotobook
{
    public function get_books()
    {
        $books = parent::get_books();

        return array_merge($books, $this->model->get_extra_books());
    }

    public function get_num_books()
    {
        return $this->data['num_books'] + count($this->model->get_extra_books());
    }

    public function get_next_book()
    {
        return null;
    }

    public function get_previous_book()
    {
        return null;
    }

    public function get_parent()
    {
        return null;
    }
}

