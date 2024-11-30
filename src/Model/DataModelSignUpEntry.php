<?php

use App\Legacy\Database\DataIter;
use App\Legacy\Database\DataModel;
use Symfony\Component\Form\Form;

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
        return get_model('DataModelSignUpForm')->get_iter($this['form_id']);
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
            ? get_model('DataModelMember')->get_iter($this['member_id'])
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

class DataModelSignUpEntry extends DataModel
{
    public $dataiter = 'DataIterSignUpEntry';

    public function __construct($db)
    {
        parent::__construct($db, 'sign_up_entries');
    }

    public function insert(DataIter $iter)
    {
        $out = parent::insert($iter);
        $this->_save_values($iter);
        return $out;
    }

    public function update(DataIter $iter)
    {
        $out = parent::update($iter);
        $this->_save_values($iter);
        return $out;
    }

    public function get_values(DataIter $iter)
    {
        $rows = $this->db->query("SELECT field_id, value FROM sign_up_entry_values WHERE entry_id = :id", false, [':id' => $iter['id']]);
        return array_column($rows, 'value', 'field_id');
    }

    protected function _generate_query($where)
    {
        return parent::_generate_query($where) . " ORDER BY created_on ASC";
    }

    private function _save_values(DataIter $iter)
    {
        // If the iter did not change the values, ignore this call
        if (!isset($iter->data['values']))
            return;

        if (!$iter->has_id())
            throw new LogicException('_save_values on iter without id');

        $this->db->beginTransaction();

        // Delete the old values
        $this->db->delete('sign_up_entry_values', 'entry_id = :id', [':id' => $iter['id']]);

        // Insert the new values
        foreach ($iter->get_values() as $field_id => $value) // get_values to skip cache
            $this->db->insert('sign_up_entry_values', [
                'entry_id' => $iter['id'],
                'field_id' => $field_id,
                'value' => $value
            ]);

        $this->db->commit();
    }
}
