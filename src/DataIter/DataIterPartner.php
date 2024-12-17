<?php

namespace App\DataIter;

use App\DataModel\DataModelPartner;
use App\Legacy\Database\DataIter;
use App\Legacy\Database\SearchResultInterface;

class DataIterPartner extends DataIter implements SearchResultInterface
{
    static public function fields()
    {
        return [
            'id',
            'name',
            'type',
            'url',
            'logo_url',
            'logo_dark_url',
            'profile',
            'has_banner_visible',
            'has_profile_visible',
            'created_on',
        ];
    }

    public function get_search_relevance(): float
    {
        return floatval($this->data['search_relevance']);
    }

    public function get_search_type(): string
    {
        return 'partner';
    }

    public function get_logo($width=null)
    {
        return get_filemanager_url($this['logo_url'], $width);
    }

    public function get_logo_dark($width=null)
    {
        return get_filemanager_url($this['logo_dark_url'], $width);
    }

    public function get_vacancies()
    {
        return $this->model->get_vacancies_for_iter($this);
    }

    public function get_sort_order()
    {
        switch ($this['type'] ?? DataModelPartner::TYPE_SPONSOR)
        {
            case DataModelPartner::TYPE_MAIN_SPONSOR:
                return 0;
            case DataModelPartner::TYPE_SPONSOR:
                return 1;
            default:
                return 2;
        }
    }
}
