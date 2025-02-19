<?php

namespace App\DataModel;

use App\DataIter\DataIterMember;
use App\DataIter\DataIterPoll;
use App\DataIter\DataIterPollComment;
use App\DataModel\DataModelMember;
use App\DataModel\DataModelPoll;
use App\DataModel\DataModelPollCommentLike;
use App\Legacy\Database\DataModel;
use App\Legacy\Database\SearchProviderInterface;
use Symfony\Component\DependencyInjection\Attribute\Lazy;

class DataModelPollComment extends DataModel implements SearchProviderInterface
{
    public string $dataiter = DataIterPollComment::class;
    public string $table = 'poll_comments';

    public static function getName(): string
    {
        return __('poll comments');
    }

    public function __construct(
        private DataModelMember $memberModel,
        #[Lazy] private DataModelPoll $pollModel,  // Lazy to prevent circular dependencies
        #[Lazy] private DataModelPollCommentLike $likeModel,  // Lazy to prevent circular dependencies
    ) {
    }

    protected function _generate_query($where)
    {
        if (is_array($where))
            $where = $this->_generate_conditions_from_array($where);

        $where = $where ?: "1=1"; // no conditions means where true :)
        return "
             SELECT poll_comments.*
                   ,COUNT(DISTINCT pl.id) AS like_count
               FROM poll_comments
               LEFT JOIN poll_comment_likes AS pl ON pl.poll_comment_id = poll_comments.id
              WHERE {$where}
              GROUP BY poll_comments.id
            ;"
        ;
    }

    public function get_for_poll(DataIterPoll $poll)
    {
        $rows = $this->db->query(
            'SELECT pc.*
                   ,COUNT(DISTINCT pl.id) AS like_count
               FROM poll_comments AS pc
               LEFT JOIN poll_comment_likes AS pl ON pl.poll_comment_id = pc.id
              WHERE poll_id = :poll_id
              GROUP BY pc.id
              ORDER BY created_on ASC
            ;',
            false,
            [
                'poll_id' => $poll->get_id(),
            ],
        );
        return $this->_rows_to_iters($rows);
    }

    public function search(string $query, ?int $limit = null): array
    {
        // More or less analogous to DataModelAgenda
        $_query = "
            WITH
                search_items AS (
                    SELECT
                        id,
                        setweight(to_tsvector(comment), 'A') body
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

    public function get_likes_for_iter(DataIterPollComment $iter)
    {
        return $this->likeModel->get_for_poll_comment($iter);
    }

    public function get_poll_for_iter(DataIterPollComment $iter)
    {
        return $this->pollModel->get_iter($iter['poll_id']);
    }

    public function get_member_for_iter(DataIterPollComment $iter)
    {
        return $this->memberModel->get_iter($iter['member_id']);
    }

    public function is_liked_by(DataIterPollComment $iter, DataIterMember $member)
    {
        return $this->likeModel->get_liked_by($iter, $member) > 0;
    }
}
