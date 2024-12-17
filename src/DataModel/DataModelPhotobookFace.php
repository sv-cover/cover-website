<?php

namespace App\DataModel;

use App\DataIter\DataIterPhoto;
use App\DataIter\DataIterPhotobook;
use App\DataIter\DataIterPhotobookFace;
use App\DataIter\DataIterFacesPhotobook;
use App\DataModel\DataModelMember;
use App\DataModel\DataModelPhotobook;
use App\DataModel\DataModelPhotobookPrivacy;
use App\Legacy\Database\DataIter;
use App\Legacy\Database\DataModel;
use App\Utils\HumanizeUtils;
use Symfony\Component\DependencyInjection\Attribute\Lazy;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

class DataModelPhotobookFace extends DataModel
{
    public string $dataiter = DataIterPhotobookFace::class;
    public string $table = 'foto_faces';

    public function __construct(
        private ContainerBagInterface $params,
        private DataModelMember $memberModel,
        #[Lazy] private DataModelPhotobook $photobookModel, // Lazy to prevent circular dependencies
        #[Lazy] private DataModelPhotobookPrivacy $privacyModel, // Lazy to prevent circular dependencies
    ) {
    }

    /**
     * Find all tags/faces for a given photo.
     *
     * @var DataIterPhoto $photo
     * @return DataIterPhotobookFace[] faces
     */
    public function get_for_photo(DataIterPhoto $photo)
    {
        return $this->find(sprintf('foto_faces.foto_id = %d', $photo->get_id()));
    }

    public function get_for_book(DataIterPhotobook $book)
    {
        return $this->find(sprintf("foto_faces.foto_id IN (SELECT id FROM fotos WHERE boek = %d AND hidden = 'f')", $book->get_id()));
    }

    /**
     * Get photo book of all photos in which each photo all $members are tagged together.
     *
     * @var DataIterMember[] $members
     * @return DataIterFacesPhotobook
     */
    public function get_book(array $members)
    {
        return new DataIterFacesPhotobook($this, $this->photobookModel, -1, [
            'titel' => sprintf(__('Photos of %s'),
                HumanizeUtils::join(array_map(fn($member) => member_first_name($member), $members))),
            'datum' => null,
            'parent_id' => 0,
            'member_ids' => array_map(fn($member) => $member->get_id(), $members),
        ]);
    }

    /**
     * Start a python process in the background to detect faces in the photos.
     *
     * @var DataIterPhoto[] $photos
     * @return int pid
     */
    public function refresh_faces(array $photos)
    {
        $photo_ids = array();

        foreach ($photos as $photo)
            $photo_ids[] = $photo->get_id();

        $command = strtr(
            '{python} {script} {photos_dir} {photo_ids} >> {log_path} 2>&1 & echo $!',
            [
                '{python}' => escapeshellcmd($this->params->get('app.facedetect.python_path')),
                '{script}' => escapeshellarg($this->params->get('app.facedetect.script_path')),
                '{photos_dir}' => escapeshellarg($this->params->get('app.photos_dir')),
                '{photo_ids}' => implode(' ', $photo_ids),
                '{log_path}' => escapeshellarg($this->params->get('app.facedetect.log_path')),
            ],
        );

        $pid = shell_exec($command);

        if (is_null($pid))
            throw new \Exception("Could not start suggest_faces process");

        return intval(rtrim($pid, " "));
    }

    public function get_center_of_interest(array $photos)
    {
        if (count($photos) === 0)
            return [];

        $photo_ids = \array_column($photos, 'id');

        $query = $this->db->query(sprintf("
            SELECT
                foto_id,
                SUM(x * w * h) / SUM(w * h) as x,
                SUM(y * w * h) / SUM(w * h) as y
            FROM
                foto_faces
            WHERE
                foto_id IN (%s)
                AND deleted = False
            GROUP BY
                foto_id", implode(',', $photo_ids)));

        return $this->_rows_to_table($query, 'foto_id', ['x', 'y']);
    }

    public function get_suggested_member(DataIterPhotobookFace $face)
    {
        if ($face['cluster_id'] === null)
            return null;

        $suggestion = $this->db->query_first("
            SELECT
                ff.lid_id,
                COUNT(ff.*) as cnt
            FROM
                fotos f
            LEFT JOIN fotos f2 ON
                f2.boek = f.boek
            LEFT JOIN foto_faces ff ON
                ff.foto_id = f2.id
                AND ff.cluster_id = :cluster_id
            WHERE
                f.id = :foto_id
            GROUP BY
                ff.lid_id
            HAVING
                ff.lid_id IS NOT NULL
            ORDER BY
                cnt DESC
            LIMIT 1",
            false,
            [
                ':foto_id' => $face['foto_id'],
                ':cluster_id' => $face['cluster_id']
            ]);

        return $suggestion
            ? $memberModel->get_iter($suggestion['lid_id'])
            : null;
    }

    /**
     * @override
     */
    protected function _generate_query($where)
    {
        return "SELECT
            foto_faces.id,
            foto_faces.foto_id,
            foto_faces.x,
            foto_faces.y,
            foto_faces.w,
            foto_faces.h,
            foto_faces.lid_id,
            foto_faces.tagged_by,
            foto_faces.custom_label,
            foto_faces.cluster_id,
            l.id as lid__id,
            l.voornaam as lid__voornaam,
            l.tussenvoegsel as lid__tussenvoegsel,
            l.achternaam as lid__achternaam,
            l.privacy as lid__privacy,
            t.voornaam as tagged_by__voornaam,
            t.tussenvoegsel as tagged_by__tussenvoegsel,
            t.achternaam as tagged_by__achternaam,
            t.privacy as tagged_by__privacy,
            (SELECT COUNT(1)
                FROM foto_hidden f_h
                WHERE
                    f_h.foto_id = foto_faces.foto_id
                    AND f_h.lid_id = foto_faces.lid_id
            ) as hidden
            FROM {$this->table}
            LEFT JOIN leden l ON l.id = foto_faces.lid_id
            LEFT JOIN leden t ON t.id = foto_faces.tagged_by
            WHERE foto_faces.deleted = FALSE " . ($where ? ' AND ' . $where : '');
    }

    /**
     * @override
     */
    protected function _delete($table, DataIter $iter)
    {
        $this->db->update($table,
            array('deleted' => 'TRUE'),
            $this->_id_string($iter->get_id()),
            array('deleted'));
    }

    public function get_members_for_book(DataIterFacesPhotobook $iter)
    {
        return array_map([$this->memberModel, 'get_iter'], $iter['member_ids']);
    }

    public function get_privacy_for_book(DataIterFacesPhotobook $iter)
    {
        return $this->privacyModel->find(sprintf('lid_id IN(%s)', implode(',', $iter->get('member_ids'))));
    }

    public function get_photo_for_face(DataIterPhotobookFace $iter)
    {
        return $this->photobookModel->get_iter($iter['foto_id']);
    }

    public function get_member_for_face(DataIterPhotobookFace $iter)
    {
        if (isset($iter->data['lid__id'])) {
            $data = [];

            foreach ($iter->data as $k => $v)
                if (str_starts_with($k, 'lid__'))
                    $data[substr($k, strlen('lid__'))] = $v;

            return $this->memberModel->new_iter($data);
        } elseif ($iter['lid_id']) {
            return $this->memberModel->get_iter($iter['lid_id']);
        } else {
            return null;
        }
    }
}
