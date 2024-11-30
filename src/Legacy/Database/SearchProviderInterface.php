<?php

namespace App\Legacy\Database;

interface SearchProviderInterface
{
    public function search(string $query, ?int $limit = null): array;
}
