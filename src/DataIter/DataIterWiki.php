<?php

namespace App\DataIter;

use App\Legacy\Database\DataIter;
use App\Legacy\Database\SearchResultInterface;

class DataIterWiki extends DataIter implements SearchResultInterface
{
    static public function fields()
    {
        return [];
    }

    public function get_search_relevance(): float
    {
        return normalize_search_rank($this->get('score'));
    }

    public function get_search_type(): string
    {
        return 'wiki';
    }

    public function get_absolute_path(): string
    {
        return sprintf($this->model->params->get('wiki_public_url'), $this->get('id'));
    }
}
