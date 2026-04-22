<?php

namespace App\DataModel;

use App\DataIter\DataIterMember;
use App\DataIter\DataIterPollComment;
use App\DataIter\DataIterPollCommentLike;
use App\Legacy\Database\DataModel;

class DataModelPollCommentLike extends DataModel
{
    public string $dataiter = DataIterPollCommentLike::class;
    public string $table = 'poll_comment_likes';

    public function get_for_poll_comment(DataIterPollComment $poll_comment)
    {
        $rows = $this->db->query(
            'SELECT *
               FROM poll_comment_likes
              WHERE poll_comment_id = :poll_comment_id
            ;',
            false,
            [
                'poll_comment_id' => $poll_comment->get_id(),
            ],
        );
        return $this->_rows_to_iters($rows);
    }

    public function get_liked_by(DataIterPollComment $poll_comment, DataIterMember $member)
    {
        return $this->db->query_value(
            'SELECT COUNT(id)
               FROM poll_comment_likes
              WHERE poll_comment_id = :poll_comment_id
                AND member_id = :member_id
            ;',
            [
                'poll_comment_id' => $poll_comment->get_id(),
                'member_id' => $member->get_id(),
            ],
        );
    }

    public function like(DataIterPollComment $poll_comment, DataIterMember $member)
    {
        $this->db->insert($this->table, [
            'poll_comment_id' => $poll_comment->get_id(),
            'member_id' => $member->get_id(),
        ]);
    }

    public function unlike(DataIterPollComment $poll_comment, DataIterMember $member)
    {
        $this->db->delete($this->table, 'poll_comment_id = :poll_comment_id AND member_id = :member_id', [
            'poll_comment_id' => $poll_comment->get_id(),
            'member_id' => $member->get_id(),
        ]);
    }
}
