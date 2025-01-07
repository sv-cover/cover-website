<?php

namespace App\DataIter;

use App\Exception\NotFoundException;
use App\Legacy\Database\DataIter;
use App\Utils\PhotoBookUtils;

class DataIterPhoto extends DataIter
{
    const EXIF_ORIENTATION_180 = 3;
    const EXIF_ORIENTATION_90_RIGHT = 6;
    const EXIF_ORIENTATION_90_LEFT = 8;

    const LANDSCAPE = 'landscape';
    const PORTRAIT = 'portrait';
    const SQUARE = 'square';

    private $_scope = null; // Photo book in which this photo is currently viewed.

    static public function fields()
    {
        return [
            'id',
            'boek',
            'beschrijving',
            'filepath',
            'filehash',
            'width',
            'height',
            'created_on',
            'added_on',
            'sort_index'
        ];
    }

    public function get_size()
    {
        return array($this->get('width'), $this->get('height'));
    }

    public function get_scaled_size($max_width = null, $max_height = null)
    {
        $size = $this->get_size();

        if ($size[0] == 0 || $size[1] == 0)
            return [null, null, null];

        if ($max_width) {
            $width = $max_width;
            $height = round($max_width * ($size[1] / $size[0]));
        }

        if (!$max_width || ($max_height && $height > $max_height)) {
            $height = $max_height;
            $width = round($max_height * ($size[0] / $size[1]));
        }

        return array($width, $height, $width / $size[0]);
    }

    public function get_orientation()
    {
        list($width, $height) = $this->get_size();

        if ($width == $height)
            return self::SQUARE;
        if ($width > $height)
            return self::LANDSCAPE;
        else
            return self::PORTRAIT;
    }

    public function get_faces()
    {
        return $this->model->get_faces_for_photo($this);
    }

    public function get_comments()
    {
        return $this->model->get_comments_for_photo($this);
    }

    public function get_likes()
    {
        return $this->model->get_likes_for_photo($this);
    }

    public function compute_size()
    {
        if (!$this->file_exists())
            throw new NotFoundException("Could not find original file {$this->get('filepath')}");

        if ($exif_data = $this->get_exif_data()) {
            $size = [
                'width' => $exif_data['COMPUTED']['Width'],
                'height' => $exif_data['COMPUTED']['Height']
            ];

            if (isset($exif_data['Orientation'])
                && ($exif_data['Orientation'] == self::EXIF_ORIENTATION_90_LEFT
                    || $exif_data['Orientation'] == self::EXIF_ORIENTATION_90_RIGHT))
                list($size['width'], $size['height']) = [$size['height'], $size['width']];

            return $size;
        }
        else if ($size = @getimagesize($this->get_full_path()))
            return [
                'width' => $size[0],
                'height' => $size[1]
            ];
        else
            throw new \RuntimeException("Could not determine image dimensions of photo {$this->get('filepath')}");
    }

    public function compute_hash()
    {
        if (!$this->file_exists())
            throw new NotFoundException("Could not find original file {$this->get('filepath')}");

        return hash_file('CRC32', $this->get_full_path(), false);
    }

    public function compute_created_on_timestamp()
    {
        if (!$this->file_exists())
            throw new NotFoundException("Could not find original file {$this->get('filepath')}");

        $exif_data = $this->get_exif_data();

        if (!$exif_data)
            return new \DateTime();

        return date('Y-m-d H:i:s', isset($exif_data['DateTimeOriginal'])
            ? strtotime($exif_data['DateTimeOriginal'])
            : $exif_data['FileDateTime']);
    }

    public function original_has_changed()
    {
        return $this->compute_hash() == $this->get('filehash');
    }

    public function get_book()
    {
        return $this->model->get_book($this->get('boek'));
    }

    public function get_full_path()
    {
        return PhotoBookUtils::path_concat($this->model->params->get('app.photos_dir'), $this->get('filepath'));
    }

    public function file_exists()
    {
        return file_exists($this->get_full_path());
    }

    public function get_exif_data()
    {
        return @exif_read_data($this->get_full_path());
    }

    public function get_file_size()
    {
        return filesize($this->get_full_path());
    }

    public function get_scope()
    {
        return $this->_scope ?: $this['book'];
    }

    public function set_scope(DataIterPhotobook $book)
    {
        if (!$book->has_photo($this))
            throw new \LogicException('Book assigned as scope says it does not contain this photo');

        $this->_scope = $book;
    }
}

