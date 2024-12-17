<?php

namespace App\DataIter;

use App\Legacy\Database\DataIter;

class DataIterMailinglistSubscription extends DataIter
{
    static public function fields()
    {
        return [
            'abonnement_id',
            'mailinglijst_id',
            'lid_id',
            'naam',
            'email',
            'ingeschreven_op',
            'opgezegd_op',
        ];
    }

    public function get_mailinglist()
    {
        return $this->model->get_mailinglist_for_iter($this);
    }

    public function get_lid()
    {
        return $this->model->get_member_for_iter($this);
    }

    public function is_active()
    {
        return empty($this['opgezegd_op']) || new \DateTime($this['opgezegd_op']) >= new \DateTime();
    }

    public function cancel()
    {
        return $this->model->cancel_subscription($this);
    }
}

