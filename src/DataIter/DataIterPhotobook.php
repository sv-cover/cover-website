<?php

namespace App\DataIter;

use App\DataIter\DataIterPhoto;
use App\DataModel\DataModelPhotobook;
use App\Legacy\Database\DataIter;
use App\Legacy\Database\SearchResultInterface;

class DataIterPhotobook extends DataIter implements SearchResultInterface
{
    static public function fields()
    {
        return [
            'id',
            'parent_id',
            'titel',
            'fotograaf',
            'date',
            'last_update',
            'beschrijving',
            'visibility',
            'sort_index',
        ];
    }

    private $_photos = null; // cache the results of DataModelPhotobook::get_photos for this book.

    public function new_book()
    {
        // Create new iter with defaults
        return new DataIterPhotobook($this->model, null, [
            'parent_id' => $this['id'],
            'visibility' => DataModelPhotobook::VISIBILITY_PUBLIC,
        ]);
    }

    public function get_books()
    {
        return $this->model->get_children($this);
    }

    public function get_books_without_metadata()
    {
        return $this->model->get_children($this, 0);
    }

    public function get_photos()
    {
        return $this->model->get_photos($this);
    }

    public function has_photo(DataIterPhoto $needle)
    {
        foreach ($this['photos'] as $photo)
            if ($photo->get_id() == $needle->get_id())
                return true;

        return false;
    }

    public function get_next_photo(DataIterPhoto $current, $num = 1)
    {
        $photos = $this['photos'];

        foreach ($photos as $index => $photo)
            if ($photo->get_id() == $current->get_id())
                break;

        if (count($photos) == $index + 1)
            return array();

        return array_slice($photos, $index + 1, min(max($num, 0), count($photos) - $index));
    }

    public function get_previous_photo(DataIterPhoto $current, $num = 1)
    {
        $photos = $this['photos'];

        foreach ($photos as $index => $photo)
            if ($photo->get_id() == $current->get_id())
                break;

        if ($index === 0)
            return array();

        return array_reverse(array_slice($photos,
            max($index - max($num, 0), 0),
            min(max($num, 0), $index)));
    }

    public function get_neighbours(DataIterPhoto $current)
    {
        $neighbours = new \stdClass();

        $prev = $this->get_previous_photo($current);
        $neighbours->previous = count($prev) > 0 ? $prev[0] : null;

        $next = $this->get_next_photo($current);
        $neighbours->next = count($next) > 0 ? $next[0] : null;

        return $neighbours;
    }

    public function get_parent()
    {
        return $this->model->get_book($this->get('parent_id'));
    }

    public function get_search_relevance(): float
    {
        if (empty($this['date']))
            return 0.5;

        $date = \DateTime::createFromFormat('d-m-Y', $this['date']);

        $recency = $date
            ? (1.0 / (time() - $date->getTimestamp()))
            : 0.0;

        return 0.7 + $recency;
    }

    public function get_search_type(): string
    {
        return 'photobook';
    }

    public function get_key_photos($limit)
    {
        $photos = $this->model->get_photos_recursive($this);

        if (!count($photos))
            return null;

        $likes = $this->model->count_for_photos($photos);

        usort($photos, function(DataIterPhoto $left, DataIterPhoto $right) use ($likes) {
            return $likes[$right->get_id()] - $likes[$left->get_id()];
        });

        return array_slice($photos, 0, $limit);
    }
}
