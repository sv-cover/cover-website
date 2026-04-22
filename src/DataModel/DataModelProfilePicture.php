<?php

namespace App\DataModel;

use App\DataIter\DataIterMember;
use App\DataIter\DataIterProfilePicture;
use App\DataModel\DataModelMember;
use App\Legacy\Database\DataIter;
use App\Legacy\Database\DataIterNotFoundException;
use App\Legacy\Database\DataModel;
use Symfony\Component\DependencyInjection\Attribute\Lazy;

class DataModelProfilePicture extends DataModel
{
    const VISIBILITY_PUBLIC = 0;
    const VISIBILITY_MEMBERS = 1;
    const VISIBILITY_ACTIVE_MEMBERS = 2;

    public string $dataiter = DataIterProfilePicture::class;
    public string $table = 'profile_pictures';

    public function __construct(
        #[Lazy] private DataModelMember $memberModel, // Lazy to prevent circular dependencies
    ) {
    }

    public function get_stream(DataIterProfilePicture $iter)
    {
        $query = "
            SELECT photo
                  ,length(photo) AS filesize
              FROM {$this->table}
             WHERE id = :id
             LIMIT 1
        ";
        return $this->db->query_first($query, false, [':id' => $iter->get_id()]);
    }

    public function get_for_member(DataIterMember $member)
    {
        $query = "
            SELECT id
                  ,member_id
                  ,created_on
                  ,reviewed
              FROM {$this->table}
             WHERE reviewed IS NOT NULL
               AND member_id = :member_id
             ORDER BY created_on DESC
             LIMIT 1
        ";

        $row = $this->db->query_first($query, false, [':member_id' => $member->get_id()]);

        if ($row === null)
            throw new DataIterNotFoundException($member->get_id(), $this);

        return $this->_row_to_iter($row);
    }

    public function set_for_member(DataIterMember $member, $fh)
    {
        $this->db->insert($this->table, [
            'member_id' => $member->get_id(),
            'photo' => $fh,
            'reviewed' => false,
        ]);
        $last_id = $this->db->get_last_insert_id();

        // Delete all old pictures for this member. We're doing this afterwards,
        // just in case something went wrong earlier.
        $delete_query = "
            DELETE FROM {$this->table}
             WHERE member_id = :member_id
               AND id != :last_id
        ";
        $this->db->execute($delete_query, [
            ':member_id' => $member->get_id(),
            ':last_id' => $last_id,
        ]);
    }

    protected function _generate_query($where)
    {
        if (is_array($where))
            $where = $this->_generate_conditions_from_array($where);

        return "
            SELECT id
                  ,member_id
                  ,created_on
                  ,reviewed
              FROM {$this->table}
        " . ($where ? " WHERE {$where}" : "") .
        " ORDER BY created_on DESC";
    }

    public function get_member_for_iter(DataIterProfilePicture $iter)
    {
        return $this->memberModel->get_iter($iter['member_id']);
    }
}
