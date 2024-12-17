<?php

namespace App\Legacy\Database;

interface SearchProviderInterface
{
    public static function getName(): string;

    public function search(string $query, ?int $limit = null): array;
}
