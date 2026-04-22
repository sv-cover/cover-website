<?php

namespace App\DataIter;

use App\DataIter\DataIterMember;
use App\Legacy\Database\DataIter;

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
        return $this->model->get_fields_for_iter($this);
    }

    public function get_entries()
    {
        return $this->model->get_entries_for_iter($this);
    }

    public function get_agenda_item()
    {
        return $this['agenda_id'] ? $this->model->get_event_for_iter($this) : null;
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
        $open_on = is_string($this['open_on']) ? new \DateTime($this['open_on']) : $this['open_on'];
        if (!$open_on || $open_on > new \DateTime())
            return false;

        $closed_on = is_string($this['closed_on']) ? new \DateTime($this['closed_on']) : $this['closed_on'];
        if ($closed_on && $closed_on < new \DateTime())
            return false;

        if (!empty($this['participant_limit']) && $this['signup_count'] >= $this['participant_limit'])
            return false;

        return true;
    }

    public function new_entry(bool $prefill = false)
    {
        return $this->model->new_entry_for_iter($this, $prefill);
    }

    public function get_entries_for_member(DataIterMember $member)
    {
        return $this->model->get_entries_for_member($this, $member);
    }
}
