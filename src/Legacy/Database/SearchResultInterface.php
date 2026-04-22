<?php

namespace App\Legacy\Database;

interface SearchResultInterface
{
    public function get_search_relevance(): float;

    public function get_search_type(): string;
}
