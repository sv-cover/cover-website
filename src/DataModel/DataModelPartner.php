<?php

namespace App\DataModel;

use App\Bridge\Filemanager;
use App\DataIter\DataIterPartner;
use App\DataModel\DataModelVacancy;
use App\Legacy\Database\DataIter;
use App\Legacy\Database\DataModel;
use App\Legacy\Database\SearchProviderInterface;
use Symfony\Component\DependencyInjection\Attribute\Lazy;

class DataModelPartner extends DataModel implements SearchProviderInterface
{
    const TYPE_SPONSOR = 0;
    const TYPE_MAIN_SPONSOR = 1;
    const TYPE_OTHER = 2;

    const TYPE_OPTIONS = [
        self::TYPE_SPONSOR => 'Sponsor',
        self::TYPE_MAIN_SPONSOR => 'Main sponsor',
        self::TYPE_OTHER => 'Other',
    ];

    public string $dataiter = DataIterPartner::class;
    public string $table = 'partners';

    public static function getName(): string
    {
        return __('partners');
    }

    public function __construct(
        public Filemanager $filemanager,
        #[Lazy] private DataModelVacancy $vacancyModel, // Lazy to prevent circular dependencies
    ) {
    }

    public function update(DataIter $iter)
    {
        $iter['last_modified'] = new DateTime();

        return parent::update($iter);
    }

    public function search(string $search_query, ?int $limit = null): array
    {
        // More or less analogous to DataModelAgenda
        $query = "
            WITH
                search_items AS (
                    SELECT
                        id,
                        setweight(to_tsvector(name), 'A') || setweight(to_tsvector(profile), 'D') body
                    FROM
                        {$this->table}
                    WHERE
                        has_profile_visible = 1
                ),
                matching_items AS (
                    SELECT
                        id,
                        body,
                        ts_rank_cd(body, query) as search_relevance
                    FROM
                        search_items,
                        plainto_tsquery('english', :keywords) query
                    WHERE
                        body @@ query
                )
            SELECT
                p.*,
                m.search_relevance
            FROM
                matching_items m
            LEFT JOIN {$this->table} p ON
                p.id = m.id
            ";

        if ($limit !== null)
            $query .= sprintf(" LIMIT %d", $limit);

        $rows = $this->db->query($query, false, [':keywords' => $search_query]);
        return $this->_rows_to_iters($rows);
    }

    public function get_vacancies_for_iter(DataIterPartner $iter)
    {
        return $this->vacancyModel->find(['partner_id' => $iter->get_id()]);
    }
}
