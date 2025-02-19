<?php

namespace App\DataModel;

use App\DataIter\DataIterMailinglistQueue;
use App\DataModel\DataModelMailinglist;
use App\Legacy\Database\DataModel;

class DataModelMailinglistQueue extends DataModel
{
    public string $dataiter = DataIterMailinglistQueue::class;
    public string $table = 'mailinglijsten_queue';

    public function __construct(
        private DataModelMailinglist $mailinglistModel,
    ) {
    }

    public function queue($message, $destination, $destination_type, $list=null, $status='waiting')
    {
        $data = [
            'destination' => $destination,
            'destination_type' => $destination_type,
            'mailinglist_id' => $list ? $list->get('id') : null,
            'message' => $message,
            'status' => $status,
        ];

        $iter = $this->new_iter($data);

        $this->insert($iter);
    }

    public function get_mailinglist_for_iter(DataIterMailinglistQueue $iter)
    {
        return $this->mailinglistModel->get_iter($iter['mailinglist_id']);
    }
}
