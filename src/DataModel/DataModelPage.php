<?php

namespace App\DataModel;

use App\DataIter\DataIterPage;
use App\DataModel\DataModelCommissie;
use App\Legacy\Database\DataIter;
use App\Legacy\Database\DataModel;
use App\Legacy\Database\SearchProviderInterface;
use App\Utils\SearchUtils;
use Symfony\Component\DependencyInjection\Attribute\Lazy;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

/**
 * A class implementing the page data
 */
class DataModelPage extends DataModel implements SearchProviderInterface
{
    public string $dataiter = DataIterPage::class;
    public string $table = 'pages';

    public static function getName(): string
    {
        return __('pages');
    }

    public function __construct(
        public ContainerBagInterface $params,
        #[Lazy] private DataModelCommissie $committeeModel, // Lazy to prevent circular dependencies
    ) {
    }

    /**
     * Gets a page from a title
     * @title the title of the page
     *
     * @result a #DataIter or null of no such page could be
     * found
     */
    public function get_iter_from_title($title)
    {
        return $this->find_one(['titel' => $title]);
    }

    public function get_iter_from_slug($slug)
    {
        return $this->find_one(['slug' => $slug]);
    }

    public function get_content($id)
    {
        return $this->get_iter($id)->get_locale_content();
    }

    public function get_title($id)
    {
        return $this->get_iter($id)->get_title();
    }

    public function get_summary($id)
    {
        return $this->get_iter($id)->get_summary();
    }

    public function search(string $search_query, ?int $limit = null): array
    {
        // TODO: This thing barely works. Try searching the contact page.

        $preferred_language = i18n_get_language();

        $weights = [];

        foreach (['en', 'nl'] as $language)
            $weights[$language] = $preferred_language == $language ? 1.0 : 0.9;

        $language_table = implode(',',
            array_map(
                function($lang, $weight) {
                    return sprintf("('%s', %f)", $lang, $weight);
                },
                array_keys($weights),
                array_values($weights)));

        $query = "
            WITH
                -- Use weights as tiebreakers when results from both languages match equally well
                weights AS (
                    SELECT
                        v.*
                    FROM (VALUES $language_table) as v (language, weight)
                ),
                search_results AS (
                    SELECT
                        id,
                        ts_rank_cd(to_tsvector('english', content_en), query) as search_relevance,
                        'en' as search_language
                    FROM
                        {$this->table},
                        plainto_tsquery('english', :query) query
                    WHERE
                        to_tsvector('english', content_en) @@ query
                UNION
                    SELECT
                        id,
                        ts_rank_cd(to_tsvector(content), query) as search_relevance,
                        'nl' as search_language
                    FROM
                        {$this->table},
                        plainto_tsquery('dutch', :query) query
                    WHERE
                        to_tsvector('dutch', content) @@ query
                ),
                unique_search_results AS (
                    SELECT
                        s.id,
                        s.search_relevance,
                        s.search_language
                    FROM
                        search_results s
                    LEFT JOIN weights w ON
                        w.language = s.search_language
                    LEFT JOIN search_results s2 ON
                        s.id = s2.id
                    LEFT JOIN weights w2 ON
                        w2.language = s2.search_language
                    WHERE
                        -- Find the result with the largest weighted search relevance
                        w.weight * s.search_relevance > w2.weight * s2.search_relevance
                )
            SELECT
                p.*,
                s.*
            FROM
                unique_search_results s
            LEFT JOIN {$this->table} p ON
                p.id = s.id
            ORDER BY
                s.search_relevance DESC
        ";

        if ($limit !== null)
            $query .= sprintf(" LIMIT %d", $limit);

        $rows = $this->db->query($query, false, [':query' => $search_query]);
        $iters = $this->_rows_to_iters($rows);

        $keywords = SearchUtils::parseQuery($search_query);

        $pattern = sprintf('/(%s)/i', implode('|', array_map(function($p) { return preg_quote($p, '/'); }, $keywords)));

        // Enhance search relevance score when the keywords appear in the title of a page :D
        foreach ($iters as $iter)
        {
            $keywords_in_title = preg_match_all($pattern, $iter->get_title('nl'))
                               + preg_match_all($pattern, $iter->get_title('en'));

            $iter->set('search_relevance', $iter->get('search_relevance') + $keywords_in_title);
        }

        return $iters;
    }

    public function insert(DataIter $iter)
    {
        $iter['last_modified'] = new \DateTime();

        return parent::insert($iter);
    }

    public function update(DataIter $iter)
    {
        $iter['last_modified'] = new \DateTime();

        return parent::update($iter);
    }

    public function get_committee_for_iter(DataIterPage $iter)
    {
        return $this->committeeModel->get_iter($iter['committee_id']);
    }
}
