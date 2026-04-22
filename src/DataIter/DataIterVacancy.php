<?php

namespace App\DataIter;

use App\Legacy\Database\DataIter;
use App\Legacy\Database\SearchResultInterface;

class DataIterVacancy extends DataIter implements SearchResultInterface
{
    static public function fields()
    {
        return [
            'id',
            'title',
            'description',
            'type',
            'study_phase',
            'url',
            'partner_id',
            'partner_name',
            'created_on',
            'updated_on',
        ];
    }

    public function get_search_relevance(): float
    {
        return floatval($this->data['search_relevance']);
    }

    public function get_search_type(): string
    {
        return 'vacancy';
    }

    public function get_partner()
    {
        if (isset($this->data['partner_id']))
            return $this->model->get_partner_for_iter($this);
        return null;
    }

    public function set($field, $value)
    {
        // Fix for limitations of the valication chain.
        if ($field == 'partner_name' && empty($value))
            $value = null;
        return parent::set($field, $value);
    }
}
