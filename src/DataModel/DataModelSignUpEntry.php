<?php

namespace App\DataModel;

use App\DataIter\DataIterSignUpEntry;
use App\DataModel\DataModelMember;
use App\DataModel\DataModelSignUpForm;
use App\Legacy\Database\DataIter;
use App\Legacy\Database\DataModel;
use Symfony\Component\DependencyInjection\Attribute\Lazy;

class DataModelSignUpEntry extends DataModel
{
    public string $dataiter = DataIterSignUpEntry::class;
    public string $table = 'sign_up_entries';

    public function __construct(
        #[Lazy] private DataModelMember $memberModel, // Lazy to prevent circular dependencies
        #[Lazy] private DataModelSignUpForm $formModel, // Lazy to prevent circular dependencies
    ) {
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
            throw new \LogicException('_save_values on iter without id');

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

    public function get_form_for_iter(DataIterSignUpEntry $iter)
    {
        return $this->formModel->get_iter($iter['form_id']);
    }

    public function get_member_for_iter(DataIterSignUpEntry $iter)
    {
        return $this->memberModel->get_iter($iter['member_id']);
    }
}
