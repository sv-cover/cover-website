<?php

namespace App\DataModel;

use App\DataIter\DataIterAnnouncement;
use App\DataModel\DataModelCommissie;
use App\Legacy\Database\DataModel;
use App\Legacy\Database\SearchProviderInterface;

class DataModelAnnouncement extends DataModel implements SearchProviderInterface
{
    const VISIBILITY_PUBLIC = 0;
    const VISIBILITY_MEMBERS = 1;
    const VISIBILITY_ACTIVE_MEMBERS = 2;

    public string $dataiter = DataIterAnnouncement::class;
    public string $table = 'announcements';

    public static function getName(): string
    {
        return __('announcements');
    }

    public function __construct(
        private DataModelCommissie $committeeModel,
    ) {
    }

    protected function _id_string($id, $table = null)
    {
        return sprintf("%s.id = %d", $table !== null ? $table : $this->table, $id);
    }

    /* protected */ function _generate_query($conditions)
    {
        return "SELECT
                {$this->table}.id,
                {$this->table}.committee_id,
                {$this->table}.subject,
                {$this->table}.message,
                TO_CHAR({$this->table}.created_on, 'DD-MM-YYYY, HH24:MI') AS created_on,
                {$this->table}.visibility,
                c.id as committee__id,
                c.naam as committee__naam,
                c.login as committee__login,
                c.page_id as committee__page_id
            FROM
                {$this->table}
            LEFT JOIN commissies c ON
                c.id = {$this->table}.committee_id"
            . ($conditions ? " WHERE $conditions" : "")
            . " ORDER BY {$this->table}.created_on DESC";
    }

    public function get_latest($count = 5)
    {
        $query = $this->_generate_query('') . ' LIMIT ' . intval($count);

        $rows = $this->db->query($query);

        return $this->_rows_to_iters($rows);
    }

    public function search(string $query, ?int $limit = null): array
    {
        $query = $this->db->escape_string($query);

        $query = $this->_generate_query("subject ILIKE '%{$query}%' OR message ILIKE '%{$query}%'");

        if ($limit !== null)
            $query = sprintf('%s LIMIT %d', $query, $limit);

        $rows = $this->db->query($query);

        return $this->_rows_to_iters($rows);
    }

    public function get_committee_for_iter(DataIterAnnouncement $iter)
    {
        $data = [];

        foreach ($iter->data as $k => $v)
            if (str_starts_with($k, 'committee__'))
                $data[substr($k, strlen('committee__'))] = $v;

        return $this->committeeModel->new_iter($data);
    }
}
