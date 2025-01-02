<?php

namespace App\DataIter;

use App\DataModel\DataModelCommissie;
use App\Legacy\Database\DataIter;
use App\Legacy\Database\SearchResultInterface;

class DataIterCommissie extends DataIter implements SearchResultInterface
{
    private $mascots;

    static public function fields()
    {
        return [
            'id',
            'type',
            'naam',
            'login',
            'website',
            'page_id',
            'hidden',
            'vacancies',
        ];
    }

    public function get_page()
    {
        return $this->model->get_page_for_iter($this);
    }

    public function get_member_ids()
    {
        return $this->model->get_member_ids($this);
    }

    public function get_members()
    {
        return $this->model->get_members($this);
    }

    public function set_members(array $members)
    {
        return $this->model->set_members($this, $members);
    }

    public function get_mascots()
    {
        if (!isset($this->mascots))
        {
            try {
                $data = \file_get_contents('public/images/mascots/data.json');
                $this->mascots = \json_decode($data, true);
            } catch (\Exception $e) {
                $this->mascots = [];
            }
        }

        return isset($this->mascots[$this['login']])
            ? $this->mascots[$this['login']]
            : [];
    }

    public function get_summary()
    {
        return $this->model->get_summary_for_iter($this);
    }

    public function get_search_relevance(): float
    {
        return floatval($this->data['search_relevance']);
    }

    public function get_search_type(): string
    {
        return 'committee';
    }

    public function has_vacancy_deadline()
    {
        return strtotime($this->get('vacancies')) < strtotime('+1 year');
    }

    public function has_vacancy()
    {
        return $this['vacancies'] && strtotime($this['vacancies']) > time();
    }

    public function get_email()
    {
        return strstr($this['login'], '@') ? $this['login'] : $this['login'] . '@svcover.nl';
    }

    public function get_email_addresses()
    {
        return $this->model->get_email_addresses($this);
    }

    public function is_type($type)
    {
        if ($type === 'committee')
            return $this['type'] === DataModelCommissie::TYPE_COMMITTEE;
        elseif ($type === 'working_group')
            return $this['type'] === DataModelCommissie::TYPE_WORKING_GROUP;
        else
            return $this['type'] === DataModelCommissie::TYPE_OTHER;
    }

    public function get_search_member()
    {
        if (!empty($this['search_match_committee_member_id']))
            return $this->model->get_member_for_search_result($this);
        return null;
    }
}
