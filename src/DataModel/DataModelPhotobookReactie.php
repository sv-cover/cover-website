<?php

namespace App\DataModel;

use App\DataIter\DataIterPhoto;
use App\DataIter\DataIterPhotobookReactie;
use App\DataModel\DataModelMember;
use App\DataModel\DataModelPhotobook;
use App\DataModel\DataModelPhotobookPrivacy;
use App\Legacy\Database\DataModel;
use Symfony\Component\DependencyInjection\Attribute\Lazy;

class DataModelPhotobookReactie extends DataModel
{
    public string $dataiter = DataIterPhotobookReactie::class;
    public string $table = 'foto_reacties';

    public function __construct(
        private DataModelMember $memberModel,
        #[Lazy] private DataModelPhotobook $photobookModel, // Lazy to prevent circular dependencies
    ) {
    }

    public function get_for_photo(DataIterPhoto $photo)
    {
        return $this->find(sprintf('foto_reacties.foto = %d', $photo['id']));
    }

    public function get_latest($num)
    {
        $rows = $this->db->query("
                SELECT
                    f_r.id,
                    f_r.foto,
                    f_r.auteur,
                    f_r.reactie,
                    f_r.date,
                    l.id as auteur__id,
                    l.voornaam as auteur__voornaam,
                    l.tussenvoegsel as auteur__tussenvoegsel,
                    l.achternaam as auteur__achternaam,
                    l.privacy as auteur__privacy,
                    fotos.beschrijving AS foto__beschrijving,
                    fotos.id AS foto__id,
                    fotos.boek AS foto__boek,
                    fotos.width AS foto__width,
                    fotos.height AS foto__height,
                    foto_boeken.id AS fotoboek__id,
                    foto_boeken.titel AS fotoboek__titel,
                    COUNT(f_r_l.id) as likes
                FROM
                    (SELECT * FROM foto_reacties ORDER BY date DESC LIMIT 10) as f_r
                LEFT JOIN foto_reacties_likes f_r_l ON
                    f_r_l.reactie_id = f_r.id
                LEFT JOIN leden l ON
                    f_r.auteur = l.id
                LEFT JOIN fotos ON
                    fotos.id = f_r.foto
                LEFT JOIN foto_boeken ON
                    foto_boeken.id = fotos.boek
                WHERE
                    fotos.hidden = 'f'
                GROUP BY
                    f_r.id,
                    f_r.foto,
                    f_r.auteur,
                    f_r.reactie,
                    f_r.date,
                    l.id,
                    l.voornaam,
                    l.tussenvoegsel,
                    l.achternaam,
                    l.privacy,
                    fotos.id,
                    fotos.beschrijving,
                    fotos.boek,
                    foto_boeken.id,
                    foto_boeken.titel
                ORDER BY
                    f_r.date DESC
                LIMIT " . intval($num));

        return $this->_rows_to_iters($rows);
    }

    protected function _generate_query($where)
    {
        return "SELECT
            foto_reacties.id,
            foto_reacties.foto,
            foto_reacties.auteur,
            foto_reacties.reactie,
            foto_reacties.date,
            (SELECT COUNT(f_r_l.id) FROM foto_reacties_likes f_r_l WHERE f_r_l.reactie_id = foto_reacties.id) as likes
            FROM {$this->table}
            " . ($where ? " WHERE {$where}" : "") . "
            ORDER BY date ASC";
    }

    public function get_member_for_iter(DataIterPhotobookReactie $iter)
    {
        if (isset($iter->data['auteur__id'])) {
            $data = [];

            foreach ($iter->data as $k => $v)
                if (str_starts_with($k, 'auteur__'))
                    $data[substr($k, strlen('auteur__'))] = $v;

            return $this->memberModel->new_iter($data);
        } elseif ($iter['foto']) {
            return $this->memberModel->get_iter($iter['auteur']);
        } else {
            return null;
        }
    }

    public function get_photo_for_iter(DataIterPhotobookReactie $iter)
    {
        if (isset($iter->data['foto__id'])) {
            $data = [];

            foreach ($iter->data as $k => $v)
                if (str_starts_with($k, 'foto__'))
                    $data[substr($k, strlen('foto__'))] = $v;

            return $this->photobookModel->new_iter($data);
        } elseif ($iter['foto']) {
            return $this->photobookModel->get_iter($iter['foto']);
        } else {
            return null;
        }
    }

    public function get_photobook_for_iter(DataIterPhotobookReactie $iter)
    {
        if (isset($iter->data['fotoboek__id'])) {
            $data = [];

            foreach ($iter->data as $k => $v)
                if (str_starts_with($k, 'fotoboek__'))
                    $data[substr($k, strlen('fotoboek__'))] = $v;

            return $this->photobookModel->new_photobook($data);
        } elseif ($iter['foto']) {
            return $iter['photo']['book'];
        } else {
            return null;
        }
    }

    public function get_liked_by_for_iter(DataIterPhotobookReactie $iter)
    {
        return $this->memberModel->find(sprintf('id IN (SELECT lid_id FROM foto_reacties_likes WHERE reactie_id = %d)', $iter['id']));
    }
}
