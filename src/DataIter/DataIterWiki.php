<?php

namespace App\DataIter;

use App\Legacy\Database\DataIter;
use App\Legacy\Database\SearchResultInterface;
use App\Utils\SearchUtils;

class DataIterWiki extends DataIter implements SearchResultInterface
{
    static public function fields()
    {
        return [];
    }

    public function get_search_relevance(): float
    {
        return SearchUtils::normalizeRank($this->get('score'));
    }

    public function get_search_type(): string
    {
        return 'wiki';
    }

    public function get_absolute_path(): string
    {
        return $this->model->wiki->getPageUrl($this->get_id());
    }
}
