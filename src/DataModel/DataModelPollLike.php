<?php

namespace App\DataModel;

use App\DataIter\DataIterMember;
use App\DataIter\DataIterPoll;
use App\DataIter\DataIterPollLike;
use App\Legacy\Database\DataModel;

class DataModelPollLike extends DataModel
{
    public string $dataiter = DataIterPollLike::class;
    public string $table = 'poll_likes';

    public function get_for_poll(DataIterPoll $poll)
    {
        $rows = $this->db->query(
            'SELECT *
               FROM poll_likes
              WHERE poll_id = :poll_id
            ;',
            false,
            [
                'poll_id' => $poll->get_id(),
            ],
        );
        return $this->_rows_to_iters($rows);
    }

    public function get_liked_by(DataIterPoll $poll, DataIterMember $member)
    {
        return $this->db->query_value(
            'SELECT COUNT(id)
               FROM poll_likes
              WHERE poll_id = :poll_id
                AND member_id = :member_id
            ;',
            [
                'poll_id' => $poll->get_id(),
                'member_id' => $member->get_id(),
            ],
        );
    }

    public function like(DataIterPoll $poll, DataIterMember $member)
    {
        $this->db->insert($this->table, [
            'poll_id' => $poll->get_id(),
            'member_id' => $member->get_id(),
        ]);
    }

    public function unlike(DataIterPoll $poll, DataIterMember $member)
    {
        $this->db->delete($this->table, 'poll_id = :poll_id AND member_id = :member_id', [
            'poll_id' => $poll->get_id(),
            'member_id' => $member->get_id(),
        ]);
    }
}
