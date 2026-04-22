<?php

namespace App\DataIter;

use App\Legacy\Database\DataIter;
use App\Legacy\Database\SearchResultInterface;

class DataIterPoll extends DataIter implements SearchResultInterface
{
    static public function fields()
    {
        return [
            'id',
            'member_id',
            'committee_id',
            'question',
            'created_on',
            'updated_on',
            'closed_on',
        ];
    }

    public function get_search_relevance(): float
    {
        return floatval($this->data['search_relevance']);
    }

    public function get_search_type(): string
    {
        return 'poll';
    }

    public function get_options()
    {
        return $this->model->get_options($this);
    }

    public function set_options(array $options)
    {
        return $this->model->set_options($this, $options);
    }

    public function get_likes()
    {
        return $this->model->get_likes_for_iter($this);
    }

    public function get_comments()
    {
        return $this->model->get_comments_for_iter($this);
    }

    public function get_committee()
    {
        // has to be isset because board unfortunately has id=0. has to be $this->data, because reasons
        if (isset($this->data['committee_id']))
            return $this->model->get_committee_for_iter($this);
        return null;
    }

    public function get_member()
    {
        if (!empty($this['member_id']))
            return $this->model->get_member_for_iter($this);
        return null;
    }

    public function get_member_has_voted(DataIterMember $member = null)
    {
        return $this['member_vote'] !== null;
    }

    public function get_member_vote(DataIterMember $member = null)
    {
        return $this->model->get_member_vote($this, $member);
    }

    public function get_total_votes()
    {
        return $this->model->get_total_votes($this);
    }

    public function get_is_open()
    {
        return empty($this['closed_on']) || new \DateTime($this['closed_on']) > new \DateTime();
    }

    public function is_liked_by(DataIterMember $member)
    {
        return $this->model->is_liked_by($this, $member);
    }
}
