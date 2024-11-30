<?php

use App\Legacy\Database\DataIter;
use App\Legacy\Database\DataModel;
use App\Legacy\Database\SearchResultInterface;
use App\Legacy\Database\SearchProviderInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class DataIterPartner extends DataIter implements SearchResultInterface
{
    static public function fields()
    {
        return [
            'id',
            'name',
            'type',
            'url',
            'logo_url',
            'logo_dark_url',
            'profile',
            'has_banner_visible',
            'has_profile_visible',
            'created_on',
        ];
    }

    public function get_search_relevance(): float
    {
        return floatval($this->data['search_relevance']);
    }

    public function get_search_type(): string
    {
        return 'partner';
    }

    public function get_logo($width=null)
    {
        return get_filemanager_url($this['logo_url'], $width);
    }

    public function get_logo_dark($width=null)
    {
        return get_filemanager_url($this['logo_dark_url'], $width);
    }

    public function get_vacancies()
    {
        return get_model('DataModelVacancy')->find(['partner_id' => $this->get_id()]);
    }

    public function get_sort_order()
    {
        switch ($this['type'] ?? DataModelPartner::TYPE_SPONSOR)
        {
            case DataModelPartner::TYPE_MAIN_SPONSOR:
                return 0;
            case DataModelPartner::TYPE_SPONSOR:
                return 1;
            default:
                return 2;
        }
    }
}

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

    public $dataiter = 'DataIterPartner';

    public function __construct($db)
    {
        parent::__construct($db, 'partners');
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
}
