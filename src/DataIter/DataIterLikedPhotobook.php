<?php

namespace App\DataIter;

use App\DataIter\DataIterPhotobook;

class DataIterLikedPhotobook extends DataIterPhotobook
{
    public function get_id()
    {
        return 'liked';
    }

    public function get_books()
    {
        return [];
    }

    public function get_books_without_metadata()
    {
        return [];
    }

    public function get_photos()
    {
        return $this->model->find('fotos.id IN (' . implode(',', $this->get('photo_ids')) . ')');
    }
}
