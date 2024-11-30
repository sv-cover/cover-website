<?php

use App\Legacy\Database\DataIter;
use App\Legacy\Database\DataModel;
use App\SignUp\SignUpFormManager;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Uid\Uuid;

class DataIterSignUpForm extends DataIter
{
    private $_form;

    static public function fields()
    {
        return [
            'id',
            'committee_id',
            'created_on',
            'open_on',
            'closed_on',
            'participant_limit',
            'agenda_id'
        ];
    }

    public function get_fields()
    {
        return get_model('DataModelSignUpField')->find(['form_id' => $this['id']]);
    }

    public function get_entries()
    {
        return get_model('DataModelSignUpEntry')->find(['form_id' => $this['id']]);
    }

    public function get_agenda_item()
    {
        return $this['agenda_id'] ? get_model('DataModelAgenda')->get_iter($this['agenda_id']) : null;
    }

    public function get_description()
    {
        return sprintf('Sign-up form #%d', $this['id']);
    }

    public function get_signup_count()
    {
        return $this->data['signup_count'] ?? count($this['entries']);
    }

    public function is_open()
    {
        $open_on = is_string($this['open_on']) ? new DateTime($this['open_on']) : $this['open_on'];
        if (!$open_on || $open_on > new DateTime())
            return false;

        $closed_on = is_string($this['closed_on']) ? new DateTime($this['closed_on']) : $this['closed_on'];
        if ($closed_on && $closed_on < new DateTime())
            return false;

        if (!empty($this['participant_limit']) && $this['signup_count'] >= $this['participant_limit'])
            return false;

        return true;
    }

    public function new_entry()
    {
        return get_model('DataModelSignUpEntry')->new_iter([
            'form_id' => $this['id'],
            'created_on' => date('Y-m-d H:i:s')
        ]);
    }

    public function get_entries_for_member(DataIterMember $member)
    {
        return get_model('DataModelSignUpEntry')->find([
            'form_id' => $this['id'],
            'member_id' => $member['id']
        ]);
    }
}

class DataModelSignUpForm extends DataModel
{
    public $dataiter = 'DataIterSignUpForm';

    public function __construct($db)
    {
        parent::__construct($db, 'sign_up_forms');
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
}