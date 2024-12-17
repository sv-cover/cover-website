<?php

namespace App\DataModel;

use App\DataIter\DataIterMember;
use App\DataIter\DataIterSignUpForm;
use App\DataModel\DataModelAgenda;
use App\DataModel\DataModelSignUpEntry;
use App\DataModel\DataModelSignUpField;
use App\Legacy\Database\DataModel;
use Symfony\Component\DependencyInjection\Attribute\Lazy;

class DataModelSignUpForm extends DataModel
{
    public string $dataiter = DataIterSignUpForm::class;
    public string $table = 'sign_up_forms';

    public function __construct(
        #[Lazy] private DataModelAgenda $eventModel, // Lazy to prevent circular dependencies
        #[Lazy] private DataModelSignUpEntry $entryModel, // Lazy to prevent circular dependencies
        #[Lazy] private DataModelSignUpField $fieldModel, // Lazy to prevent circular dependencies
    ) {
    }

    protected function _generate_query($where)
    {
        if (is_array($where))
            $where = $this->_generate_conditions_from_array($where);

        $WHERE = $where ? " WHERE {$where}" : "";

        return "
            SELECT
                {$this->table}.*,
                COUNT(sign_up_entries.id) as signup_count
            FROM
                {$this->table}
            LEFT JOIN sign_up_entries ON
                sign_up_entries.form_id = {$this->table}.id
            {$WHERE}
            GROUP BY
                {$this->table}.id,
                {$this->table}.committee_id,
                {$this->table}.agenda_id,
                {$this->table}.created_on,
                {$this->table}.open_on,
                {$this->table}.closed_on";
    }

    public function get_entries_for_iter(DataIterSignUpForm $iter)
    {
        return $this->entryModel->find(['form_id' => $iter['id']]);
    }

    public function get_fields_for_iter(DataIterSignUpForm $iter)
    {
        return $this->fieldModel->find(['form_id' => $iter['id']]);
    }

    public function get_event_for_iter(DataIterSignUpForm $iter)
    {
        return $this->eventModel->get_iter($iter['agenda_id']);
    }

    public function new_entry_for_iter(DataIterSignUpForm $iter)
    {
        return $this->entryModel->new_iter([
            'form_id' => $iter['id'],
            'created_on' => date('Y-m-d H:i:s')
        ]);
    }

    public function get_entries_for_member(DataIterSignUpForm $iter, DataIterMember $member)
    {
        return $this->entryModel->find([
            'form_id' => $iter['id'],
            'member_id' => $member['id']
        ]);
    }
}
