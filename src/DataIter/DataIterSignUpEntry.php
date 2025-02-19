<?php

namespace App\DataIter;

use App\Legacy\Database\DataIter;

class DataIterSignUpEntry extends DataIter
{
    public $errors = [];

    static public function fields()
    {
        return [
            'id',
            'form_id',
            'member_id',
            'created_on',
        ];
    }

    public function get_form()
    {
        return $this->model->get_form_for_iter($this);
    }

    public function get_form_data()
    {
        $data = [];

        foreach ($this['form']['fields'] as $field)
            $data = array_merge($data, $field->get_form_data($this));

        return $data;
    }

    public function get_member()
    {
        return $this['member_id']
            ? $this->model->get_member_for_iter($this)
            : null;
    }

    public function set_values(array $field_values)
    {
        $this->data['values'] = array_filter($field_values, function($value) { return $value !== null; });
    }

    public function get_values()
    {
        if (isset($this->data['values']))
            return $this->data['values'];

        if (!$this['id'])
            return [];

        return $this->model->get_values($this);
    }
}
