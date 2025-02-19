<?php

namespace App\DataIter;

use App\Legacy\Database\DataIter;

class DataIterSignUpField extends DataIter
{
    static public function fields()
    {
        return [
            'id',
            'form_id',
            'name',
            'type',
            'properties',
            'sort_index',
            'deleted'
        ];
    }

    public function get_properties()
    {
        try {
            return json_decode($this->data['properties'], true);
        } catch (Exception $e) {
            return [];
        }
    }

    public function set_properties(array $properties)
    {
        $this->data['properties'] = json_encode($properties);
    }

    public function get_form()
    {
        return $this->model->get_form_for_iter($this);
    }
}
