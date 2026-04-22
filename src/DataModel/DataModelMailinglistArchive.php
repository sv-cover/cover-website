<?php

namespace App\DataModel;

use App\DataIter\DataIterMailinglist;
use App\DataIter\DataIterMailinglistArchive;
use App\Legacy\Database\DataModel;

class DataModelMailinglistArchive extends DataModel
{
    public string $dataiter = DataIterMailinglistArchive::class;
    public string $table = 'mailinglijsten_berichten';

    public function archive($bericht, $sender, $lijst, $commissie, $return_code)
    {
        $data = array(
            'bericht' => $bericht,
            'sender' => $sender,
            'mailinglijst' => $lijst ? $lijst->get('id') : null,
            'commissie' => $commissie ? $commissie->get('id') : null,
            'return_code' => $return_code
        );

        $iter = $this->new_iter($data);

        $this->insert($iter);
    }

    public function get_for_list(DataIterMailinglist $list)
    {
        return $this->find(['mailinglijst' => (int) $list['id']]);
    }

    public function count_for_list(DataIterMailinglist $list, $span_in_days = null)
    {
        $query = sprintf("SELECT COUNT(id) FROM {$this->table} WHERE mailinglijst = %d AND return_code = 0", $list->get_id());

        if ($span_in_days !== null)
            $query .= sprintf(" AND verwerkt_op > CURRENT_DATE - INTERVAL '%d days'", $span_in_days);

        return (int) $this->db->query_value($query);
    }

    public function contains_email_from(DataIterMailinglist $lijst, $sender)
    {
        $count = $this->db->query_value(sprintf("SELECT COUNT(id) FROM {$this->table} WHERE mailinglijst = %d AND sender = '%s' AND return_code = 0",
            $lijst->get_id(), $this->db->escape_string($sender)));

        return $count > 0;
    }

    protected function _generate_query($where)
    {
        return parent::_generate_query($where) . ' ORDER BY verwerkt_op DESC';
    }
}
