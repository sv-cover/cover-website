<?php

namespace App\DataModel;

use App\DataIter\DataIterPhoto;
use App\DataIter\DataIterPhotobook;
use App\DataIter\DataIterRootPhotobook;
use App\DataModel\DataModelPhotobookFace;
use App\DataModel\DataModelPhotobookReactie;
use App\DataModel\DataModelPhotobookLike;
use App\Legacy\Database\DataIter;
use App\Legacy\Database\DataIterNotFoundException;
use App\Legacy\Database\DataModel;
use App\Legacy\Database\SearchProviderInterface;
use App\Policy\PolicyPhotobook;
use App\Service\Authentication;
use App\Utils\SearchUtils;
use Symfony\Component\DependencyInjection\Attribute\Lazy;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

/**
 * A class implementing photo data
 */
class DataModelPhotobook extends DataModel implements SearchProviderInterface
{
    const VISIBILITY_PUBLIC = 0;
    const VISIBILITY_MEMBERS = 1;
    const VISIBILITY_ACTIVE_MEMBERS = 2;
    const VISIBILITY_PHOTOCEE = 3;

    const READ_STATUS = 1;
    const NUM_BOOKS = 2;
    const NUM_PHOTOS = 4;

    const READ_STATUS_READ = 'read';
    const READ_STATUS_UNREAD = 'unread';

    public string $dataiter = DataIterPhoto::class;
    public string $table = 'fotos';

    public static function getName(): string
    {
        return __('photo books');
    }

    public function __construct(
        public Authentication $auth,
        public ContainerBagInterface $params,
        #[Lazy] private DataModelPhotobookFace $faceModel, // Lazy to prevent circular dependencies
        #[Lazy] private DataModelPhotobookReactie $commentModel, // Lazy to prevent circular dependencies
        #[Lazy] private DataModelPhotobookLike $likeModel, // Lazy to prevent circular dependencies
        #[Lazy] private PolicyPhotobook $policy, // Lazy to prevent circular dependencies
    ) {
    }

    public function new_photobook($row = array())
    {
        return parent::new_iter($row, DataIterPhotobook::class);
    }

    /**
     * Get a photo book
     * @id the id of the book
     *
     * @result a #DataIter
     */
    public function get_book($id)
    {
        if ($id == 0)
            return $this->get_root_book();

        $q = sprintf("
                SELECT
                    f_b.*,
                    COUNT(DISTINCT f.id) as num_photos,
                    COUNT(DISTINCT c_f_b.id) as num_books
                FROM
                    foto_boeken f_b
                LEFT JOIN fotos f ON
                    f.boek = f_b.id
                    AND f.hidden = 'f'
                LEFT JOIN foto_boeken c_f_b ON
                    c_f_b.parent_id = f_b.id
                WHERE
                    f_b.id = %d
                GROUP BY
                    f_b.id
                ", $id);

        $row = $this->db->query_first($q);

        if ($row === null)
            throw new DataIterNotFoundException($id, $this);

        return $this->_row_to_iter($row, DataIterPhotobook::class);
    }

    public function search(string $query, ?int $limit = null): array
    {
        $sql_atoms = array_map(function($keyword) {
            return sprintf("f_b.titel ILIKE '%%%s%%'", $this->db->escape_string($keyword));
        }, SearchUtils::parseQuery($query));

        $query = "SELECT
                f_b.*,
                COUNT(DISTINCT f.id) as num_photos,
                COUNT(DISTINCT c_f_b.id) as num_books
            FROM
                foto_boeken f_b
            LEFT JOIN fotos f ON
                f.boek = f_b.id
                AND f.hidden = 'f'
            LEFT JOIN foto_boeken c_f_b ON
                c_f_b.parent_id = f_b.id
            WHERE
                " . implode(' AND ', $sql_atoms) . "
            GROUP BY
                f_b.id
            ORDER BY
                f_b.date DESC";

        if ($limit !== null)
            $query .= sprintf(' LIMIT %d', $limit);

        return $this->_rows_to_iters($this->db->query($query), DataIterPhotobook::class);
    }

    public function get_root_book()
    {
        $counts = $this->db->query_first("
            SELECT
                (SELECT COUNT(id) FROM foto_boeken WHERE parent_id = 0) as num_books,
                (SELECT COUNT(id) FROM fotos WHERE boek = 0 AND hidden = 'f') as num_photos");

        return new DataIterRootPhotobook($this, 0, array_merge(['titel' => __('Photo album')], $counts));
    }

    /**
     * Get a random photo book
     * @count the number of latest photo books to choose from
     *
     * @result a #DataIter
     */
    public function get_random_book($count = 10)
    {
        $q = sprintf("
            SELECT
                c.id
            FROM
                foto_boeken c
            LEFT JOIN fotos f ON
                f.boek = c.id
                AND f.hidden = 'f'
            WHERE
                c.visibility <= %d
                AND c.date IS NOT NULL
                AND c.titel NOT LIKE 'Regret Repository'
            GROUP BY
                c.id
            HAVING
                COUNT(f.id) > 0
            ORDER BY
                c.date DESC
            LIMIT %d",
            $this->policy->getAccessLevel(),
            intval($count));

        // Select the last $count books
        $rows = $this->db->query($q);

        // Pick a random fotoboek
        $book = $rows[rand(0, count($rows) - 1)];

        return $this->get_book($book['id']);
    }

    /**
     * Get the book before a certain book
     * @book a #DataIter representing a book
     *
     * @result a #DataIter
     */
    public function get_previous_book(DataIterPhotobook $book)
    {
        if (!$book['parent']) return null;

        $children = $book['parent']['books_without_metadata'];

        $index = array_usearch($book, $children, ['DataIter', 'is_same']);

        return $index !== null && isset($children[$index - 1])
            ? $children[$index - 1]
            : null;
    }

    /**
     * Get the book after a certain book
     * @book a #DataIter representing a book
     *
     * @result a #DataIter
     */
    public function get_next_book(DataIterPhotobook $book)
    {
        if (!$book['parent']) return null;

        $children = $book['parent']['books_without_metadata'];

        $index = array_usearch($book, $children, ['DataIter', 'is_same']);

        return $index !== null && isset($children[$index + 1])
            ? $children[$index + 1]
            : null;
    }

    /**
     * Get all the books in a certain book
     * @book a #DataIter representing a book
     *
     * @result an array of #DataIter
     */
    public function get_children(DataIterPhotobook $book, $metadata = null)
    {
        // TODO When we use PHP 5.6 this can be put as default parameter. For now, PHP does not support 'evaluated' expressions there.
        if ($metadata === null)
            $metadata = self::READ_STATUS | self::NUM_BOOKS | self::NUM_PHOTOS;

        // TODO not query the book and photo counts if their flags are not passed to $metadata.

        $select = 'SELECT
            foto_boeken.*,
            COUNT(DISTINCT fotos.id) AS num_photos,
            COUNT(DISTINCT child_books.id) as num_books';

        $from = 'FROM
            foto_boeken';

        $joins = "
            LEFT JOIN fotos ON
                foto_boeken.id = fotos.boek
                AND fotos.hidden = 'f'
            LEFT JOIN foto_boeken child_books ON
                child_books.parent_id = foto_boeken.id";

        $where = sprintf('WHERE
            foto_boeken.visibility <= %d
            AND foto_boeken.parent_id = %d',
            $this->policy->getAccessLevel(),
            $book->get_id());

        $group_by = 'GROUP BY
            foto_boeken.id,
            foto_boeken.parent_id,
            foto_boeken.beschrijving,
            foto_boeken.date,
            foto_boeken.titel,
            foto_boeken.fotograaf';

        $order_by = 'ORDER BY
            foto_boeken.sort_index ASC NULLS FIRST,
            date DESC,
            foto_boeken.id';

        if ($this->auth->loggedIn && $metadata & self::READ_STATUS) {
            $select = sprintf('
                WITH RECURSIVE book_children (id, date, last_update, visibility, parents) AS (
                    SELECT id, date, last_update, visibility, ARRAY[0] FROM foto_boeken WHERE parent_id = %d
                UNION ALL
                    SELECT f_b.id, f_b.date, f_b.last_update, f_b.visibility, b_c.parents || f_b.parent_id
                    FROM book_children b_c, foto_boeken f_b
                    WHERE b_c.id = f_b.parent_id
            )
            ', $book->get_id()) . $select;

            $select .= ",
                f_b_read_status.read_status";

            $joins .= sprintf("
                LEFT JOIN (
                    SELECT
                        foto_boeken.id,
                        CASE
                            WHEN
                                COUNT(nullif(
                                    foto_boeken.date > '%1\$d-08-23' AND -- only photo books from just before I started
                                    (f_b_v.last_visit < foto_boeken.last_update OR f_b_v.last_visit IS NULL), false)) -- and which I didn't visit yet
                                + COUNT(nullif(b_c.id IS NOT NULL AND (
                                    b_c.date >= '%1\$d-08-23' AND
                                    (f_b_c_v.last_visit < b_c.last_update OR f_b_c_v.last_visit IS NULL)
                                ), false)) > 0
                            THEN '" . self::READ_STATUS_UNREAD . "'
                            ELSE '" . self::READ_STATUS_READ . "'
                        END read_status
                    FROM
                        foto_boeken
                    LEFT JOIN book_children b_c ON
                        b_c.visibility <= %2\$d
                        AND foto_boeken.id = ANY(b_c.parents)
                    LEFT JOIN foto_boeken_visit f_b_v ON
                        f_b_v.boek_id = foto_boeken.id
                        AND f_b_v.lid_id = %3\$d
                    LEFT JOIN foto_boeken_visit f_b_c_v ON
                        f_b_c_v.boek_id = b_c.id
                        AND f_b_c_v.lid_id = %3\$d
                    GROUP BY
                        foto_boeken.id
                ) as f_b_read_status ON
                    f_b_read_status.id = foto_boeken.id",
                    $this->auth->identity->get('beginjaar') ?? date('Y'),
                    $this->policy->getAccessLevel(),
                    $this->auth->identity->get('id'));

            $group_by .= ",
                f_b_read_status.read_status";
        } else {
            $select .= ',
                \'' . self::READ_STATUS_READ . '\' as read_status';
        }

        $rows = $this->db->query("$select $from $joins $where $group_by $order_by");

        return $this->_rows_to_iters($rows, DataIterPhotobook::class);
    }

    protected function _generate_query($where)
    {
        return "
            SELECT
                {$this->table}.*,
                '" . self::READ_STATUS_READ . "' AS read_status, -- Assume this otherwise it could cause trouble with the grouping in _photos.twig
                COUNT(DISTINCT foto_reacties.id) AS num_reacties
            FROM
                {$this->table}
            LEFT JOIN foto_reacties ON
                foto_reacties.foto = {$this->table}.id
            WHERE
                {$this->table}.hidden = 'f'" . ($where ? ' AND (' . $where . ')' : '') . "
            GROUP BY
                {$this->table}.id
            ORDER BY
                {$this->table}.id ASC";
    }

    /**
     * Get a certain number of randomly selected photos
     * @num the number of random photos to select
     *
     * @result an array of #DataIter
     */
    public function get_random_photos($num)
    {
        $rows = $this->db->query(sprintf("
                SELECT
                    f.*,
                    DATE_PART('year', foto_boeken.date) AS fotoboek_jaar,
                    foto_boeken.titel as fotoboek_titel
                FROM
                    (SELECT
                        fotos.id
                    FROM
                        fotos
                    WHERE
                        fotos.hidden = 'f'
                        AND
                        fotos.boek IN (
                            SELECT
                                foto_boeken.id
                            FROM
                                foto_boeken
                            WHERE
                                foto_boeken.visibility = %d
                        )
                    ORDER BY
                        RANDOM()
                    LIMIT %d) as f_ids
                LEFT JOIN fotos f ON
                    f.id = f_ids.id
                LEFT JOIN foto_boeken ON
                    foto_boeken.id = f.boek
                GROUP BY
                    f.id,
                    foto_boeken.date,
                    foto_boeken.titel",
                    self::VISIBILITY_PUBLIC,
                    $num));

        return $this->_rows_to_iters($rows, DataIterPhoto::class);
    }

    /**
     * Get photos in a book
     * @book a #DataIter representing a book
     * @max optional; the maximum number of photos to get (specify
     * 0 for no maximum)
     * @random optional; whether to order the photos randomly
     *
     * @result an array of #DataIter
     */
    public function get_photos(DataIterPhotobook $book)
    {
        if ($this->auth->loggedIn) {
            $lid_id = $this->auth->identity->get('id');

            $read_status_select_atom = "
                CASE
                    WHEN fotos.added_on > MAX(f_b_v.last_visit)
                    THEN '" . self::READ_STATUS_UNREAD . "'
                    ELSE '" . self::READ_STATUS_READ . "'
                END read_status";

            $read_status_join_atom = "
                LEFT JOIN foto_boeken_visit f_b_v ON
                    f_b_v.boek_id = fotos.boek
                    AND f_b_v.lid_id = {$lid_id}";
        } else {
            $read_status_select_atom = "
                '" . self::READ_STATUS_READ . "' as read_status";

            $read_status_join_atom = "";
        }

        $query = "
            SELECT
                fotos.*,
                COUNT(DISTINCT foto_reacties.id) AS num_reacties,
                {$read_status_select_atom}
            FROM
                fotos
            LEFT JOIN foto_reacties ON
                foto_reacties.foto = fotos.id
            {$read_status_join_atom}
            WHERE
                boek = {$book->get_id()} -- Todo: security risk?
                AND hidden = 'f'
            GROUP BY
                fotos.id
            ORDER BY
                sort_index ASC NULLS FIRST,
                created_on ASC,
                added_on ASC";

        $rows = $this->db->query($query);

        return $this->_rows_to_iters($rows);
    }

    public function get_photos_recursive(DataIterPhotobook $book, $max = 0, $random = false, $seed = null)
    {
        $query = sprintf("
            WITH RECURSIVE book_children (id, visibility, parents) AS (
                    SELECT id, visibility, ARRAY[id] FROM foto_boeken WHERE id = %d
                UNION ALL
                    SELECT f_b.id,  f_b.visibility, b_c.parents || f_b.parent_id
                    FROM book_children b_c, foto_boeken f_b
                    WHERE b_c.id = f_b.parent_id
            )
            SELECT
                f.*
            FROM
                book_children b_c
            LEFT JOIN fotos f ON
                f.boek = b_c.id
                AND f.hidden = 'f'
            WHERE
                b_c.visibility <= %d
            GROUP BY
                f.id
            HAVING
                COUNT(f.id) > 0",
                $book->get_id(),
                $this->policy->getAccessLevel()); // BAD DEPENDENCY!

        if ($random)
            $query .= ' ORDER BY RANDOM()';

        if ($max > 0)
            $query .= sprintf(' LIMIT %d', $max);

        if ($random && $seed !== null)
            $this->db->query(sprintf("SET seed TO %s", $seed));

        $rows = $this->db->query($query);

        return $this->_rows_to_iters($rows, DataIterPhoto::class);
    }

    /**
     * Get all the parents of a book
     * @book a #DataIter representing a book
     *
     * @result an array of #DataIter
     */
    public function get_parents(DataIterPhotobook $book)
    {
        $result = array();

        while ($book = $book['parent'])
            $result[] = $book;

        return array_reverse($result);
    }

    /**
     * Delete a photo. This will automatically delete any comments on
     * and faces tagged in the photo due to database table constraints.
     *
     * @iter a #DataIter representing a photo;
     * @result whether or not the delete was successful
     */
    public function delete(DataIter $iter)
    {
        $result = parent::delete($iter);

        // Remove scaled versions of the image from the scaled image cache
        $filled_in_filter = preg_replace('/%d/', $iter->get_id(), $this->params->get('app.photos_scaled_dir'), 1);

        $filter = str_replace('%d', '*', $filled_in_filter);
        foreach (glob($filter) as $scaled_image_path)
            unlink($scaled_image_path);

        return $result;
    }

    /**
     * Insert a photo into the database. Width, height and filehash are
     * caculated if they are not already set.
     *
     * @param DataIterPhoto $photo to insert
     * @return int|boolean the photo id on success, or false on failure
     */
    public function insert(DataIter $iter)
    {
        // Determine width and height of the new image
        if (!$iter->has_value('width') || !$iter->has_value('height'))
            $iter->set_all($iter->compute_size());

        // Determine the CRC32 file hash, used for detecting changes later on
        if (!$iter->has_value('filehash'))
            $iter->set('filehash', $iter->compute_hash());

        if (!$iter->has_value('created_on'))
            $iter->set('created_on', $iter->compute_created_on_timestamp());

        if (!$iter->has_value('added_on'))
            $iter['added_on'] = new DateTime();

        return parent::insert($iter);
    }

    /**
     * Insert a book
     * @iter a #DataIter representing a book
     *
     * @result whether or not the insert was successful
     */
    public function insert_book(DataIterPhotobook $iter)
    {
        return $this->_insert('foto_boeken', $iter, true);
    }

    /**
     * Delete a book. This will also delete all photos
     * and subbooks and all accompanying face tags,
     * comments and scaled images in the cache.
     *
     * @param DataIterPhotobook $iter representing a book
     * @return boolean whether or not the delete was successful
     */
    public function delete_book(DataIterPhotobook $iter)
    {
        if (!is_numeric($iter->get_id()))
            throw new InvalidArgumentException('You can only delete real books');

        foreach ($iter->get_books() as $child)
            $this->delete_book($child);

        foreach ($iter->get_photos() as $photo)
            $this->delete($photo);

        $result = $this->_delete('foto_boeken', $iter);

        return $result;
    }

    /**
     * Update a book
     *
     * @param DataIterPhotobook $iter representing a book
     * @return boolean whether or not the update was successful
     */
    public function update_book(DataIterPhotobook $iter)
    {
        return $this->_update('foto_boeken', $iter);
    }

    /**
     * Mark a photo book as read for a certain member.
     *
     * @param int $lid_id id of the DataIterMember
     * @param DataIterPhotobook $book book to mark as read
     */
    public function mark_read($lid_id, DataIterPhotobook $book)
    {
        if (ctype_digit((string) $book->get_id()))
            $this->_mark_database_book_read($lid_id, $book);
        else
            $this->_mark_custom_book_read($lid_id, $book);
    }

    private function _mark_database_book_read($lid_id, DataIterPhotobook $book)
    {
        try {
            $this->db->insert('foto_boeken_visit',
                array(
                    'lid_id' => $lid_id,
                    'boek_id' => $book->get_id(),
                    'last_visit' => 'NOW()'
                ),
                array('last_visit'));
        } catch (\Exception $e) {
            $this->db->update('foto_boeken_visit',
                array('last_visit' => 'NOW()'),
                sprintf('lid_id = %d AND boek_id = %d', $lid_id, $book->get_id()),
                array('last_visit'));
        }
    }

    private function _mark_custom_book_read($lid_id, DataIterPhotobook $book)
    {
        try {
            $this->db->insert('foto_boeken_custom_visit',
                array(
                    'lid_id' => $lid_id,
                    'boek_id' => $book->get_id(),
                    'last_visit' => 'NOW()'
                ),
                array('last_visit'));
        } catch (\Exception $e) {
            $this->db->update('foto_boeken_custom_visit',
                array('last_visit' => 'NOW()'),
                sprintf('lid_id = %d AND boek_id = %s', $lid_id, $this->db->quote_value($book->get_id())),
                array('last_visit'));
        }
    }

    /**
     * Marks all photo books that are children of the passed in book as
     * read for a certain member.
     *
     * @param int $lid_id id of the DataIterMember
     * @param DataIterPhotobook $book parent book of which the children
     *  must be marked as read
     */
    protected function mark_children_read($lid_id, DataIterPhotobook $book)
    {
        $query = sprintf('
            WITH RECURSIVE book_children (id, visibility, parents) AS (
                    SELECT id, visibility, ARRAY[0] FROM foto_boeken WHERE parent_id = %2$d
                UNION ALL
                    SELECT f_b.id, f_b.visibility, b_c.parents || f_b.parent_id
                    FROM book_children b_c, foto_boeken f_b
                    WHERE b_c.id = f_b.parent_id
            )
            INSERT INTO foto_boeken_visit (lid_id, boek_id, last_visit)
            SELECT %1$d, b_c.id, NOW() FROM book_children b_c
            WHERE
                b_c.visibility <= %3$d
                AND NOT EXISTS (
                    SELECT 1
                    FROM foto_boeken_visit
                    WHERE lid_id = %1$d AND boek_id = b_c.id)',
            $lid_id,
            $book->get_id(),
            $this->policy->getAccessLevel());

        $this->db->query($query);
    }

    /**
     * Mark a photo book and all its children as read for a certain member.
     *
     * @param int $lid_id id of the DataIterMember
     * @param DataIterPhotobook $book book to mark as read
     */
    public function mark_read_recursively($lid_id, DataIterPhotobook $book)
    {
        $this->mark_read($lid_id, $book);

        $this->mark_children_read($lid_id, $book);
    }

    public function get_faces_for_photo(DataIterPhoto $photo)
    {
        return $this->faceModel->get_for_photo($photo);
    }

    public function get_comments_for_photo(DataIterPhoto $photo)
    {
        return $this->commentModel->get_for_photo($photo);
    }

    public function get_likes_for_photo(DataIterPhoto $photo)
    {
        return $this->likeModel->get_for_photo($photo);
    }

    public function count_likes_for_photos(array $photos)
    {
        return $this->likeModel->count_for_photos($photos);
    }

    public function get_extra_books()
    {
        if (!$this->auth->loggedIn)
            return [];

        return [
            $this->likeModel->get_book($this->auth->identity->member()),
            $this->faceModel->get_book([$this->auth->identity->member()]),
        ];
    }
}
