<?php

namespace App\DataIter;

use App\Legacy\Database\DataIter;
use App\Legacy\Database\SearchResultInterface;
use App\Utils\SearchUtils;

class DataIterAgenda extends DataIter implements SearchResultInterface
{
    static public function fields()
    {
        return [
            'id',
            'kop',
            'beschrijving',
            'committee_id',
            'van',
            'tot',
            'locatie',
            'image_url',
            'private',
            'extern',
            'facebook_id',
            'category',
            'replacement_for',
        ];
    }

    public function get_search_relevance(): float
    {
        return SearchUtils::normalizeRank($this->data['search_relevance']);
    }

    public function get_search_type(): string
    {
        return 'event';
    }

    public function get_academic_year(): string
    {
        $dt = $this->get_van_datetime() ?? new \DateTime();

        if ($dt->format('n') < 9)
            return sprintf('%d-%d', $dt->format('Y') - 1, $dt->format('Y'));
        return sprintf('%d-%d', $dt->format('Y'), $dt->format('Y') + 1);
    }

    public function get_van_datetime()
    {
        return $this['van'] ? new \DateTime($this['van']) : null;
    }

    public function get_tot_datetime()
    {
        return $this['tot'] ? new \DateTime($this['tot']) : null;
    }

    public function is_proposal()
    {
        return $this->get('replacement_for') !== null;
    }

    public function get_proposals()
    {
        return $this->model->get_proposed($this);
    }

    public function get_use_tot()
    {
        return $this['van'] != $this['tot'];
    }

    public function get_use_locatie()
    {
        return $this['locatie'];
    }

    public function get_image($width=null)
    {
        return $this->model->filemanager->getFileUrl($this['image_url'], $width);
    }

    public function get_committee()
    {
        return $this->model->get_committee_for_iter($this);
    }

    public function get_signup_forms()
    {
        return $this->model->get_signup_forms_for_iter($this);
    }

    public function get_updated_fields(DataIterAgenda $other = null)
    {
        // Still allow comparison with any DataIterAgenda if provided as other
        if (empty($this['replacement_for']) && empty($other))
            return [];

        if (empty($other))
            $other = $this->model->get_iter($this['replacement_for']);

        $updates = [];

        foreach ($this->data as $field => $value)
        {
            if ($field === 'replacement_for' || $field === 'id' || substr($field, 0, 11) === 'committee__')
                continue;

            $other_value = $other[$field];

            // Unfortunately, we need to 'normalize' the time fields for this to work
            if ($field == 'van' || $field == 'tot')
            {
                $other_value = strtotime($other[$field]);
                $value = strtotime($value);
            }

            if ($field == 'committee_id')
            {
                $other_value = ['id' => $other_value, 'name' => $other['committee__naam'], 'login' => $other['committee__login']];
                $value = ['id' => $value, 'name' => $this['committee__naam'], 'login' => $this['committee__login']];
            }

            if ($other_value != $value)
                $updates[$field] = [$value, $other_value];
        }

        return $updates;
    }
}
