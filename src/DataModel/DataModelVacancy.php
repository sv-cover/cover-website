<?php

namespace App\DataModel;

use App\DataIter\DataIterVacancy;
use App\DataModel\DataModelPartner;
use App\Legacy\Database\DatabaseLiteral;
use App\Legacy\Database\DataIter;
use App\Legacy\Database\DataModel;
use App\Legacy\Database\SearchProviderInterface;
use Symfony\Component\DependencyInjection\Attribute\Lazy;

class DataModelVacancy extends DataModel implements SearchProviderInterface
{
    const TYPE_FULL_TIME = 0;
    const TYPE_PART_TIME = 1;
    const TYPE_INTERNSHIP = 2;
    const TYPE_GRADUATION_PROJECT = 3;
    const TYPE_OTHER = 4;

    const TYPE_OPTIONS = [
        self::TYPE_FULL_TIME => 'Full-time',
        self::TYPE_PART_TIME => 'Part-time',
        self::TYPE_INTERNSHIP => 'Internship',
        self::TYPE_GRADUATION_PROJECT => 'Graduation project',
        self::TYPE_OTHER => 'Other/unknown',
    ];

    const STUDY_PHASE_BSC = 0;
    const STUDY_PHASE_MSC = 1;
    const STUDY_PHASE_BSC_GRADUATED = 2;
    const STUDY_PHASE_MSC_GRADUATED = 3;
    const STUDY_PHASE_OTHER = 4;

    const STUDY_PHASE_OPTIONS = [
        self::STUDY_PHASE_BSC => 'Bachelor Student',
        self::STUDY_PHASE_MSC => 'Master Student',
        self::STUDY_PHASE_BSC_GRADUATED => 'Graduated Bachelor',
        self::STUDY_PHASE_MSC_GRADUATED => 'Graduated Master',
        self::STUDY_PHASE_OTHER => 'Other/unknown',
    ];

    const FILTER_FIELDS = ['query', 'partner', 'study_phase', 'type'];

    public string $dataiter = DataIterVacancy::class;
    public string $table = 'vacancies';

    public static function getName(): string
    {
        return __('vacancies');
    }

    public function __construct(
        #[Lazy] private DataModelPartner $partnerModel, // Lazy to prevent circular dependencies
    ) {
    }

    public function update(DataIter $iter)
    {
        $iter['updated_on'] = new \DateTime();

        return parent::update($iter);
    }

    protected function _id_string($id, $table = null)
    {
        return sprintf("%s.id = %d", $table !== null ? $table : $this->table, $id);
    }

    protected function _generate_filter_conditions(array $conditions=[])
    {
        $search = [];
        $filter = [];

        foreach ($conditions as $field => $values) {
            if (!is_array($values))
                $values = [$values];

            foreach ($values as $val) {
                // skip empty values
                if (empty($val))
                    continue;
                if ($field === 'query') {
                    $val =  $this->db->quote_value('%' . $val . '%');
                    $search[] = sprintf('title ILIKE %s', $val);
                    $search[] = sprintf('description ILIKE %s', $val);
                } elseif ($field === 'partner') {
                    if (is_numeric($val))
                        $filter[] = sprintf('partner_id =  %s', $this->db->quote_value($val));
                    else
                        $filter[] = sprintf('partner_name ILIKE %s', $this->db->quote_value($val));
                } else {
                    $filter[] = sprintf('%s =  %s', $field, $val);
                }
            }
        }

        $prepared = [];

        if (!empty($search))
            $prepared[] = new DatabaseLiteral('(' . implode(' OR ', $search) . ')');

        if (!empty($filter))
            $prepared[] = new DatabaseLiteral('(' . implode(' OR ', $filter) . ')');

        return $prepared;
    }

    public function filter(array $conditions=[])
    {
        $filter_conditions = $this->_generate_filter_conditions($conditions);

        if (!empty($filter_conditions))
            $filter_conditions = $this->_generate_conditions_from_array($filter_conditions);

        $query = sprintf(
            "SELECT *
               FROM {$this->table}
               %s
              ORDER BY LOWER(title)
            ",
            (!empty($filter_conditions) ? " WHERE {$filter_conditions}" : "")
        );

        $rows = $this->db->query($query);

        return $this->_rows_to_iters($rows);
    }

    public function partners()
    {
        $rows = $this->db->query("
            SELECT NULL AS id
                  ,t1.name AS name
              FROM (
                    SELECT DISTINCT partner_name AS name
                      FROM {$this->table}
                     WHERE partner_name IS NOT NULL
                   ) AS t1

            UNION

            SELECT t2.id AS id
                  ,p.name AS name
              FROM partners  AS P JOIN (
                    SELECT DISTINCT partner_id AS id
                      FROM {$this->table}
                     WHERE partner_id IS NOT NULL
                   ) t2 ON p.id = t2.id;
        ");
        return $rows;
    }

    public function search(string $query, ?int $limit = null): array
    {
        // More or less analogous to DataModelAgenda
        $_query = "
            WITH
                search_items AS (
                    SELECT
                        id,
                        setweight(to_tsvector(title), 'A') || setweight(to_tsvector(description), 'D') body
                    FROM
                        {$this->table}
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
                v.*,
                m.search_relevance
            FROM
                matching_items m
            LEFT JOIN {$this->table} v ON
                v.id = m.id
            ";

        if ($limit !== null)
            $_query .= sprintf(" LIMIT %d", $limit);

        $rows = $this->db->query($_query, false, [':keywords' => $query]);
        return $this->_rows_to_iters($rows);
    }

    public function get_partner_for_iter(DataIterVacancy $iter)
    {
        return $this->partnerModel->get_iter($iter['partner_id']);
    }
}
