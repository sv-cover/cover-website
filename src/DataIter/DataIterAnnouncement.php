<?php

namespace App\DataIter;

use App\DataIter\DataIterCommissie;
use App\DataModel\DataModelPartner;
use App\Legacy\Database\DataIter;
use App\Legacy\Database\SearchResultInterface;

class DataIterAnnouncement extends DataIter implements SearchResultInterface
{
    static public function fields()
    {
        return [
            'id',
            'committee_id',
            'subject',
            'message',
            'created_on',
            'visibility',
        ];
    }

    public function get_committee()
    {
        return $this->model->get_committee_for_iter($this);
    }

    public function get_search_relevance(): float
    {
        return 0.5;
    }

    public function get_search_type(): string
    {
        return 'announcement';
    }
}
