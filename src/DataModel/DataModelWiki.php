<?php

namespace App\DataModel;

use App\Bridge\Wiki;
use App\DataIter\DataIterWiki;
use App\Legacy\Authentication\Authentication;
use App\Legacy\Database\DataModel;
use App\Legacy\Database\SearchProviderInterface;

class DataModelWiki extends DataModel implements SearchProviderInterface
{
    public string $dataiter = DataIterWiki::class;

    public function __construct(
        private Authentication $auth,
        public Wiki $wiki,
    ) {
    }

    public static function getName(): string
    {
        return __('wiki pages');
    }

    public function search(string $query, ?int $limit = null): array
    {
        $results = $this->wiki->search($query);

        $iters = [];

        foreach ($results as $result)
            $iters[] = new DataIterWiki($this, $result['id'], $result);

        return $iters;
    }
}
