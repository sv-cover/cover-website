<?php

namespace App\DataIter;

use App\Legacy\Database\DataIter;

class DataIterPhotobookReactie extends DataIter
{
    static public function fields()
    {
        return [
            'id',
            'foto',
            'auteur',
            'reactie',
            'date',
        ];
    }

    public function get_photo()
    {
        return $this->model->get_photo_for_iter($this);
    }

    public function get_photobook()
    {
        return $this->model->get_photobook_for_iter($this);
    }

    public function get_author()
    {
        return $this->model->get_member_for_iter($this);
    }

    public function get_liked_by()
    {
        return $this->model->get_liked_by_for_iter($this);
    }

    public function like(DataIterMember $member)
    {
        $this->model->db->insert('foto_reacties_likes', [
            'reactie_id' => $this->get_id(),
            'lid_id' => $member->get_id()
        ]);

        // Just assume we removed a like, and remove it from the tally
        $this->data['likes']++;
    }

    public function unlike(DataIterMember $member)
    {
        $this->model->db->delete('foto_reacties_likes',
            sprintf('reactie_id = %d AND lid_id = %d',
                $this->get_id(),
                $member->get_id()));

        // Again, lets just assume :)
        $this->data['likes']--;
    }

    public function is_liked_by(DataIterMember $member)
    {
        // Todo: fetch these instead of the count using GROUP_CONCAT?
        return $this->model->db->query_value(sprintf(
            'SELECT COUNT(id) FROM foto_reacties_likes WHERE reactie_id = %d AND lid_id = %d',
            $this->get_id(), $member->get_id())) > 0;
    }

    public function get_likes()
    {
        return (int) $this->model->db->query_value(sprintf(
            'SELECT COUNT(id) FROM foto_reacties_likes WHERE reactie_id = %d',
            $this->get_id()));
    }
}