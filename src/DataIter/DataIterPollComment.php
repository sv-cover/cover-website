<?php

namespace App\DataIter;

use App\DataIter\DataIterMember;
use App\Legacy\Database\DataIter;
use App\Legacy\Database\SearchResultInterface;

class DataIterPollComment extends DataIter implements SearchResultInterface
{
    static public function fields()
    {
        return [
            'id',
            'poll_id',
            'member_id',
            'comment',
            'question',
            'created_on',
            'updated_on',
        ];

    }

    public function get_poll()
    {
        return $this->model->get_poll_for_iter($this);
    }

    public function get_search_relevance(): float
    {
        return floatval($this->data['search_relevance']);
    }

    public function get_search_type(): string
    {
        return 'poll_comment';
    }

    public function get_likes()
    {
        return $this->model->get_likes_for_iter($this);
    }

    public function get_member()
    {
        if (!empty($this['member_id']))
            return $this->model->get_member_for_iter($this);
        return null;
    }

    public function is_liked_by(DataIterMember $member)
    {
        return $this->model->is_liked_by($this, $member);
    }
}
