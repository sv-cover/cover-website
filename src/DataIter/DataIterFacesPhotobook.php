<?php

namespace App\DataIter;

use App\DataIter\DataIterPhotobook;
use App\DataModel\DataModelPhotobook;
use App\DataModel\DataModelPhotobookFace;
use App\Legacy\Database\DataModel;

class DataIterFacesPhotobook extends DataIterPhotobook
{
    public function __construct(
        public DataModelPhotobookFace $faceModel,
        DataModel $model = null, $id, $data, array $seed = [],
    ) {
        parent::__construct($model, $id, $data, $seed);
    }

    public function get_members()
    {
        return $this->faceModel->get_members_for_book($this);
    }
    /**
     * Add a special id to this photo book, consisting of 'member_' and the
     * member ids shown in this book.
     *
     * @override
     * @return string
     */
    public function get_id()
    {
        return sprintf('member_%s', implode('_', $this['member_ids']));
    }

    /**
     * Override DataIterPhotobook::get_books because this special photo book
     * has no child books.
     *
     * @override
     * @return DataIterPhotobook[]
     */
    public function get_books()
    {
        return array();
    }

    public function get_books_without_metadata()
    {
        return array();
    }

    /**
     * Get all photos with the faces of the members of this photo book. Note
     * that this method caches the query results in $this->_cached_photos so
     * changing the member_ids value after calling this method once causes
     * undefined behavior.
     *
     * @override
     * @return DataIterPhoto[] photos with all members tagged ordered from
     * newest to oldest.
     */
    public function get_photos()
    {
        $conditions = array("fotos.hidden = 'f'");

        foreach ($this->get('member_ids') as $member_id)
            $conditions[] = sprintf('fotos.id IN (SELECT foto_id FROM foto_faces WHERE lid_id = %d AND deleted = FALSE)', $member_id);

        // Find which photos should not be shown for this set of members
        $hidden = $this->faceModel->get_privacy_for_book($this);

        // Also grab the ids of all the photos which should actually be hidden (e.g. are not of the logged in member)
        $excluded_ids = array_filter(array_map(function($iter) {
            return $this->model->auth->identity->get('id') != $iter['lid_id']
                ? $iter['foto_id']
                : false;
            }, $hidden));

        // If there are any photos that should be hidden, exclude them from the query
        if (count($excluded_ids) > 0)
            $conditions[] = sprintf('fotos.id NOT IN (%s)', implode(',', $excluded_ids));

        $photos = $this->model->find(implode("\nAND ", $conditions));

        // mark all new faces as, well, new.
        if ($this->model->auth->loggedIn) {
            $new_photos = $this->_get_new_photo_ids();

            if (count($new_photos))
                foreach ($photos as $photo)
                    if (in_array($photo['id'], $new_photos))
                        $photo->data['read_status'] = DataModelPhotobook::READ_STATUS_UNREAD;
        }

        return array_reverse($photos);
    }

    private function _get_new_photo_ids()
    {
        $sql_member_ids = implode(',', array_map([$this->db, 'quote_value'], $this['member_ids']));

        // Fetch the ids of the photos that were tagged after this book was last visited
        // There might be a few too many ids here, but that doesn't really matter
        return $this->db->query_column("
            SELECT DISTINCT
                f.id
            FROM
                foto_boeken_custom_visit v
            LEFT JOIN foto_faces ff ON
                ff.lid_id IN ($sql_member_ids)
                AND ff.deleted = FALSE
                AND ff.tagged_on > v.last_visit
            LEFT JOIN fotos f ON
                f.id = ff.foto_id
                AND f.hidden = FALSE
            WHERE
                v.boek_id = :boek_id
                AND v.lid_id = :lid_id
                AND f.id IS NOT NULL
            ",
            0, // first column
            [
                ':boek_id' => $this['id'],
                ':lid_id' => $this->model->auth->identity->get('id')
            ]);
    }

    public function get_read_status()
    {
        if ($this->model->auth->loggedIn)
            return DataModelPhotobook::READ_STATUS_READ;

        return count($this->_get_new_photo_ids()) > 0
            ? DataModelPhotobook::READ_STATUS_UNREAD
            : DataModelPhotobook::READ_STATUS_READ;
    }

    public function get_num_books()
    {
        return 0;
    }

    public function get_num_photos()
    {
        // Todo: this query is too expensive for just showing the count on the index page
        return count($this['photos']);
    }
}
