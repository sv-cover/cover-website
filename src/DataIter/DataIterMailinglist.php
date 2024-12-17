<?php

namespace App\DataIter;

use App\Legacy\Database\DataIter;

class DataIterMailinglist extends DataIter
{
    static public function fields()
    {
        return [
            'id',
            'naam',
            'adres',
            'omschrijving',
            'type',
            'publiek',
            'toegang',
            'commissie', // Why didn't I correctly name it committee_id?! :(
            'tag',
            'has_members',
            'has_contributors',
            'has_starting_year',
            'on_subscription_subject',
            'on_subscription_message',
            'on_first_email_subject',
            'on_first_email_message',
        ];
    }

    public function bevat_lid(DataIterMember $member)
    {
        return $this->model->is_subscribed($this, $member);
    }

    public function sends_email_on_subscribing()
    {
        return !empty($this['on_subscription_subject'])
            && !empty($this['on_subscription_message']);
    }

    public function sends_email_on_first_email()
    {
        return !empty($this['on_first_email_subject'])
            && !empty($this['on_first_email_message']);
    }

    public function get_subscriptions()
    {
        return $this->model->get_subscriptions($this);
    }

    public function get_committee()
    {
        return $this->model->get_committee_for_iter($this);
    }

    public function get_archive()
    {
        return $this->model->get_archive($this);
    }

    public function get_reach($partition_by = null)
    {
        return $this->model->get_reach($this, $partition_by);
    }
}
