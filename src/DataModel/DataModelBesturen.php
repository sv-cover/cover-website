<?php

namespace App\DataModel;

use App\DataIter\DataIterBestuur;
use App\DataModel\DataModelPage;
use App\Legacy\Database\DataModel;

class DataModelBesturen extends DataModel
{
    public string $dataiter = DataIterBestuur::class;
    public string $table = 'besturen';

    public function __construct(
        private DataModelPage $pageModel,
    ) {
    }

    public function get_from_page($page_id)
    {
        $hits = $this->find(sprintf('page_id = %d', $page_id));

        return $hits ? current($hits) : null;
    }

    public function get_page_for_iter(DataIterBestuur $iter)
    {
        return $this->pageModel->get_iter($iter['page_id']);
    }
}
