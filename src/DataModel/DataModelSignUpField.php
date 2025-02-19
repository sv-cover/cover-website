<?php

namespace App\DataModel;

use App\DataIter\DataIterSignUpField;
use App\DataModel\DataModelSignUpForm;
use App\Legacy\Database\DataIter;
use App\Legacy\Database\DataModel;
use Symfony\Component\DependencyInjection\Attribute\Lazy;

class DataModelSignUpField extends DataModel
{
    public string $dataiter = DataIterSignUpField::class;
    public string $table = 'sign_up_fields';

    public function __construct(
        #[Lazy] private DataModelSignUpForm $formModel, // Lazy to prevent circular dependencies
    ) {
    }

    public function update_order(array $fields)
    {
        $values = [];

        foreach (array_values($fields) as $n => $field)
            $values[] = sprintf('(%d, %d)', $field['id'], $n);

        $sql_values = implode(', ', $values);

        $this->db->query("
            UPDATE {$this->table} as t
            SET sort_index = index
            FROM (VALUES $sql_values) as v(id, index)
            WHERE v.id = t.id");
    }

    public function find($conditions)
    {
        if (is_array($conditions) && !isset($conditions['deleted']))
            $conditions['deleted'] = false;

        return parent::find($conditions);
    }

    public function insert(DataIter $iter)
    {
        if ($iter['sort_index'] === null)
            $iter['sort_index'] = $this->_next_sort_index($iter['form_id']);

        return parent::insert($iter);
    }

    private function _next_sort_index($form_id)
    {
        return $this->db->query_value("
            SELECT
                COALESCE(MAX(sort_index) + 1, 0)
            FROM
                {$this->table}
            WHERE
                form_id = :form_id
            ", [':form_id' => $form_id]);
    }

    public function delete(DataIter $iter)
    {
        $iter['deleted'] = true;
        $this->update($iter);
    }

    public function restore(DataIter $iter)
    {
        $iter['deleted'] = false;
        $this->update($iter);
    }

    protected function _generate_query($where)
    {
        return parent::_generate_query($where) . ' ORDER BY sort_index ASC NULLS LAST';
    }

    public function get_form_for_iter(DataIterSignUpField $iter)
    {
        return $this->formModel->get_iter($iter['form_id']);
    }
}
