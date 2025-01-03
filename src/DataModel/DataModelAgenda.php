<?php

namespace App\DataModel;

use App\DataModel\DataModelCommissie;
use App\DataModel\DataModelSignUpForm;
use App\DataIter\DataIterAgenda;
use App\Legacy\Database\DataIter;
use App\Legacy\Database\DataModel;
use App\Legacy\Database\SearchProviderInterface;
use App\Service\Authentication;
use App\Service\Filemanager;
use App\Utils\SearchUtils;

class DataModelAgenda extends DataModel implements SearchProviderInterface
{
    public string $dataiter = DataIterAgenda::class;
    public string $table = 'agenda';

    public static function getName(): string
    {
        return __('calendar events');
    }

    public function __construct(
        private Authentication $auth,
        public Filemanager $filemanager,
        private DataModelCommissie $committeeModel,
        private DataModelSignUpForm $signUpFormModel,
    ) {
    }

    public function get_include_private(): bool
    {
        return $this->auth->identity->is_member() || $this->auth->identity->is_donor();
    }

    public function get($from = null, $till = null, $confirmed_only = false)
    {
        $conditions = array();

        if ($from !== null)
            $conditions['van__gte'] = $from;

        if ($till !== null)
            $conditions['tot__lt'] = $till;

        if ($confirmed_only)
            $conditions['replacement_for__isnull'] = true;

        return $this->find($conditions);
    }

    protected function _generate_query($where)
    {
        if (is_array($where))
            $where = $this->_generate_conditions_from_array($where);

        return "
            SELECT
                {$this->table}.*,
                commissies.naam as committee__naam,
                commissies.login as committee__login,
                commissies.page_id as committee__page_id
            FROM
                {$this->table}
            LEFT JOIN commissies ON
                commissies.id = agenda.committee_id"
            . ($where ? " WHERE {$where}" : "")
            . " ORDER BY {$this->table}.van ASC";
    }

    /**
      * Get the currently relevant agendapunten
      * @include_prive optional; whether to also get the private
      * agendapunten
      * @result an array of #DataIter with the currently
      * relevant agendapunten
      */
    public function get_agendapunten()
    {
        return $this->find("
            CAST(agenda.van as DATE) >= CURRENT_DATE -- activities in the future
            OR CAST(agenda.tot as DATE) >= CURRENT_DATE -- activities currently ongoing
        ");
    }

    /**
      * Gets agendapunten of a specific commissie
      * @id the commissie id
      * @include_priv optional; whether or not include private
      * agendapunten
      *
      * @result an array of #DataIter
      */
    public function get_for_commissie($id, $include_prive = false)
    {
        $rows = $this->db->query("SELECT *, " .
                $this->_generate_select() . "
                FROM agenda
                WHERE (tot > CURRENT_TIMESTAMP OR
                (DATE_PART('hours', van) = 0 AND
                CURRENT_TIMESTAMP < van + interval '1 day')) AND
                agenda.replacement_for IS NULL AND
                commissie = " . $id .
                (!$include_prive ? ' AND private = 0 ' : '') . "
                ORDER BY van ASC");
    }

    public function search(string $query, ?int $limit = null): array
    {
        $fields = implode(', ', array_map(function ($field) { return "a.$field"; }, call_user_func([$this->dataiter, 'fields'])));

        $_query = "
            WITH
                search_items AS (
                    SELECT
                        id,
                        setweight(to_tsvector(kop), 'A') || setweight(to_tsvector(beschrijving), 'B') body
                    FROM
                        {$this->table}
                    WHERE
                        replacement_for IS NULL
                        " . (!$this->get_include_private() ? ' AND private = 0 ' : '') . "
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
                a.*,
                c.naam as committee__naam,
                c.page_id as committee__page_id,
                m.search_relevance
            FROM
                matching_items m
            LEFT JOIN {$this->table} a ON
                a.id = m.id
            LEFT JOIN commissies c ON
                c.id = a.committee_id
            ORDER BY
                a.van DESC
            " . ($limit !== null ? " LIMIT " . intval($limit) : "");

        $rows = $this->db->query($_query, false, [':keywords' => $query]);

        return $this->_rows_to_iters($rows);
    }

    public function delete(DataIter $iter)
    {
        /* Remove the possible moderation */
        foreach ($this->get_proposed() as $proposed_update)
            if ($proposed_update->get('replacement_for') == $iter->get_id())
                $this->reject_proposal($proposed_update);

        /* Chain up */
        return parent::delete($iter);
    }

    public function propose_insert(DataIterAgenda $new_item)
    {
        if ($new_item->has_id())
            throw new \InvalidArgumentException('How come the proposed insert already has an id?');

        $new_item->set('replacement_for', 0);
        return $this->insert($new_item);
    }

    public function propose_update(DataIterAgenda $iter)
    {
        if (!$iter->has_id())
            throw new \InvalidArgumentException('The item to replace has no id');

        $iter->set('replacement_for', $iter->get_id());
        $iter->set_id(null);
        return $this->insert($iter);
    }

    public function accept_proposal(DataIterAgenda $proposal)
    {
        if (!$proposal->is_proposal())
            throw new \InvalidArgumentException('Given agenda item iter is not a proposed update');

        // It is not a replacement, just a proposal for an insert
        if ($proposal->get('replacement_for') == 0)
        {
            $proposal->set('replacement_for', null);
            $proposal->update();
        }
        // It is an update: replace the contents of the old item (to preserve the id)
        // and throw away the proposal afterwards.
        else
        {
            $current = $this->get_iter($proposal->get('replacement_for'));

            // Copy everything but the item id and its update proposal data
            foreach (array_diff(DataIterAgenda::fields(), ['id', 'replacement_for']) as $field)
                $current->set($field, $proposal->get($field));

            $this->update($current);

            $this->delete($proposal);
        }
    }

    public function reject_proposal(DataIterAgenda $proposal)
    {
        $this->delete($proposal);
    }

    public function get_proposed(DataIterAgenda $replacements_for = null)
    {
        return $replacements_for === null
            ? $this->find("{$this->table}.replacement_for IS NOT NULL")
            : $this->find(sprintf("{$this->table}.replacement_for = %d", $replacements_for['id']));
    }

    public function find_locations($query, $limit = null)
    {
        $sql_term = $this->db->escape_string($query);

        $rows = $this->db->query("
            SELECT locatie
            FROM {$this->table}
            WHERE locatie ILIKE '%{$sql_term}%'
              AND van > (CURRENT_TIMESTAMP - INTERVAL '2 year')
            GROUP BY locatie
            ORDER BY COUNT(id) DESC"
            . ($limit !== null
                ? sprintf(' LIMIT %d', $limit)
                : ''));

        return array_column($rows, 'locatie');
    }

    public function get_committee_for_iter(DataIterAgenda $iter)
    {
        return $this->committeeModel->get_iter($iter['committee_id']);
    }

    public function get_signup_forms_for_iter(DataIterAgenda $iter)
    {
        return $this->signUpFormModel->find(['agenda_id' => $iter->get_id()]);
    }
}
