<?php

namespace App\DataModel;

use App\DataIter\DataIterMailinglist;
use App\DataModel\DataModelMailinglistArchive;

class DataModelMailinglistArchiveAdapter
{
    public function __construct(
        protected DataModelMailinglistArchive $model,
        protected DataIterMailinglist $mailing_list
    ) {
    }

    public function contains_email_from($sender)
    {
        return $this->model->contains_email_from($this->mailing_list, $sender);
    }

    public function get()
    {
        return $this->model->get_for_list($this->mailing_list);
    }

    public function count($span_in_days = null)
    {
        return $this->model->count_for_list($this->mailing_list, $span_in_days);
    }
}
