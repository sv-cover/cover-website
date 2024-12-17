<?php

namespace App\DataModel;

use App\DataIter\DataIterMember;
use App\DataModel\DataModelProfilePicture;
use App\Exception\InactiveMemberException;
use App\Legacy\Database\DataIter;
use App\Legacy\Database\DataModel;
use App\Legacy\Database\SearchProviderInterface;
use App\Legacy\Database\DataIterNotFoundException;
use App\Service\Authentication;
use App\Service\Secretary;
use Symfony\Component\DependencyInjection\Attribute\Lazy;

class DataModelMember extends DataModel implements SearchProviderInterface
{
    const STATUS_LID = 1;
    const STATUS_LID_AF = 2;
    const STATUS_ERELID = 3;
    const STATUS_DONATEUR = 5;
    const STATUS_PENDING = 6;

    const VISIBLE_TO_NONE = 0;
    const VISIBLE_TO_MEMBERS = 1;
    const VISIBLE_TO_EVERYONE = 7;

    const PRIVACY_DEFAULT = 958435335;

    public string $dataiter = DataIterMember::class;
    public string $table = 'leden';

    protected ?bool $auto_increment = false;

    public static function getName(): string
    {
        return __('members');
    }

    public function __construct(
        #[Lazy] private Authentication $auth, // Lazy to prevent circular dependencies
        #[Lazy] private DataModelProfilePicture $profilePictureModel, // Lazy to prevent circular dependencies
        public Secretary $secretary,
    ) {
    }

    public function get_jarigen()
    {
        $rows = $this->db->query('
                SELECT
                    id,
                    voornaam,
                    tussenvoegsel,
                    achternaam,
                    privacy,
                    (EXTRACT(YEAR FROM CURRENT_TIMESTAMP) - EXTRACT(YEAR FROM geboortedatum)) AS leeftijd
                FROM
                    leden
                WHERE
                    EXTRACT(MONTH FROM geboortedatum) = EXTRACT(MONTH FROM CURRENT_TIMESTAMP) AND
                    EXTRACT(DAY FROM geboortedatum) = EXTRACT(DAY FROM CURRENT_TIMESTAMP) AND
                    member_from < NOW() AND (member_till IS NULL OR member_till > NOW()) AND
                    geboortedatum <> \'1970-01-01\'
                ORDER BY
                    voornaam, tussenvoegsel, achternaam');

        return $this->_rows_to_iters($rows);
    }

    /**
     * Get limited member data (id, type, wachtwoord) from email and password combination.
     * @param $email string email address of the user
     * @param $passwd string password of the user
     * @return bool|DataIterMember
     */
    public function login($email, $passwd)
    {
        try {
            $iter = $this->get_from_email(trim($email));

            if ($iter === null)
                return false;

            if (!$this->test_password($iter, $passwd))
                return false;

            if (!$iter->is_member() && !$iter->is_donor() && !$iter->is_pending())
                // Pending members can login and will be shown a message they don't have all access
                throw new InactiveMemberException('This user is currently not a member nor a donor and can therefore not log in.');

            return $iter;
        } catch (DataIterNotFoundException $e) {
            return false;
        }
    }

    public function test_password(DataIterMember $member, $password)
    {
        $stored_password = $this->db->query_value(sprintf('SELECT password FROM passwords WHERE lid_id = %d', $member->get_id()));

        // Old md5 password
        if (preg_match('/^[a-z0-9]{32}$/', $stored_password)) {
            if (md5($password) !== $stored_password)
                return false;
        }

        // New PHP 5.5 password function crypt-like passwords
        else if (!password_verify($password, $stored_password))
            return false;

        if (password_needs_rehash($stored_password, PASSWORD_DEFAULT))
            $this->set_password($member, $password);

        return true;
    }

    public function set_password(DataIterMember $member, $new_password)
    {
        // Todo: If we are sure we have PSQL 9.5 or higher, we could do an INSERT .. ON CONFLICT UPDATE query.

        $hash = password_hash($new_password, PASSWORD_DEFAULT);

        try {
            $this->db->insert('passwords', ['lid_id' => $member->get_id(), 'password' => $hash]);

            return true;
        } catch (\PDOException $e) {
            // Assume the exception is a conflicting row. If it is not, rethrow!
            if ($e->getCode() != '23505')
                throw $e;

            $affected = $this->db->update('passwords',
                ['password' => $hash],
                sprintf('lid_id = %d', $member->get_id()));

            return $affected === 1;
        }
    }

    /**
      * Get commissies a certain member is in
      * @memberid the id of the member
      *
      * @result an array of commissie ids
      */
    public function get_commissies($memberid)
    {
        $rows = $this->db->query("SELECT committee_id
                FROM committee_members
                WHERE member_id = " . intval($memberid));

        $commissies = array();

        if (!$rows)
            return $commissies;

        foreach ($rows as $row)
            $commissies[] = $row['committee_id'];

        return $commissies;
    }

    /**
      * Get a member from an email address
      * @email the email address of the member
      *
      * @result a #DataIter or null of there is no member with
      * such an email address
      */
    public function get_from_email($email)
    {
        $iter = $this->find_one(['email__cieq' => $email]);

        if ($iter === null)
            throw new DataIterNotFoundException($email, $this);

        return $iter;
    }

    /**
      * Get the full name from a iter
      * @iter a #DataIter
      *
      * @result the members full name
      */
    public function get_full_name(DataIter $iter)
    {
        return $iter['voornaam'] . ($iter['tussenvoegsel'] ? (' ' . $iter['tussenvoegsel']) : '') . ' ' . $iter['achternaam'];
    }

    /**
      * Get all the privacy fields
      *
      * @result an array of privacy_field_name => privacy_field_id
      */
    public function get_privacy()
    {
        $rows = $this->db->query('SELECT * FROM profielen_privacy ORDER BY id ASC');

        $privacy = array();

        if (!$rows)
            return $privacy;

        foreach ($rows as $row)
            $privacy[$row['field']] = intval($row['id']);

        return $privacy;
    }

    /**
      * Returns whether a given field in iter is private or not.
      * This function determines the privacy state of the field and
      * checks if it matches with the currently logged in member
      * @iter a #DataIter containing a privacy field containing
      * the privacy bitmask information
      * @field the name of the field to check the privacy for
      * @field if true, always return true if requested iter
      * is the currently logged in member.
      * @result true if the field is private, false otherwise
      */
    public function is_private(DataIter $iter, $field, $self=false)
    {
        $value = $this->get_privacy_for_field($iter,$field);

        // Visible to all -> not private.
        if ($value == self::VISIBLE_TO_EVERYONE)
            return false;

        // If we are viewing ourself ourselves, then it isn't private, obviously ;)
        if ($this->auth->loggedIn && $self && $this->auth->identity->get('id') == $iter->get_id())
            return false;

        // Visible to none -> private.
        if ($value == self::VISIBLE_TO_NONE)
            return true;

        // Only visible to members, and I am not a member? -> private.
        // Devices also can see data only visible to members
        elseif (($value & self::VISIBLE_TO_MEMBERS) && !$this->auth->identity->is_member() && !$this->auth->identity->is_device())
            return true;

        // Otherwise, not private
        else
            return false;
    }

    /**
      * Return the privacy value for a field
      * @result integer that corresponds to privacy
      */

    public function get_privacy_for_field(DataIter $iter, $field)
    {
        static $privacy = null;

        // Hack for these three fields which are often combined, and the correct alias
        if (in_array($field, array('voornaam', 'tussenvoegsel', 'achternaam', 'full_name', 'nick')))
            $field = 'naam';


        if ($privacy == null)
            $privacy = $this->get_privacy();

        if (!array_key_exists($field, $privacy))
            return false;

        $value = ($iter['privacy'] >> ($privacy[$field] * 3)) & 7;
        return $value;
    }

    /*
     * Returns true if field is viewable for all
     *
     */
    public function privacy_public_for_field(DataIter $iter, $field)
    {
        $value = $this->get_privacy_for_field($iter,$field);
        return ($value & self::VISIBLE_TO_EVERYONE);
    }

    /**
      * Get members by searching in their first and last names.
      * Only a part of the name needs to be matched.
      *
      * TODO This method is only used by the YearbookCee export
      * currently because it allows us to return all but only
      * relevant members easily. Should be rewritten to use
      * DataIter::find() or ::get() because the search functionality
      * isn't used at all.
      *
      * @first a part of the first name to search for
      * @last a part of the last name to search for
      *
      * @result an array of #DataIter
      */
    public function get_from_search_first_last($first, $last)
    {

        $query = 'SELECT l.*, s.studie
            FROM leden l
            LEFT JOIN studies s ON s.lidid = l.id
            WHERE True';

        $order = array();

        if ($first) {
            $query .= " AND l.voornaam ILIKE '%" . $this->db->escape_string($first) . "%'";
            $order[] = 'l.voornaam';
        }

        if ($last) {
            $query .= " AND l.achternaam ILIKE '%" . $this->db->escape_string($last) . "%'";
            $order[] = 'l.achternaam';
        }

        if (count($order) > 0)
            $query .= ' ORDER BY ' . implode(', ', $order);

        $rows = $this->db->query($query);

        $rows = $this->_aggregate_rows($rows, array('studie'), 'id');

        return $this->_rows_to_iters($rows);
    }

    /**
     * @author Jelmer van der Linde
     * Group rows by $group_by_column and in the process turn all fields
     * named in $aggregate_fields into arrays. This is a bit of a dirty
     * (Ok, a really dirty replacement) for array_agg in Postgres.
     *
     * @rows the raw database rows
     * @aggregate_fields fields that need to be collected for each group
     * @group_by_column name of the column which identifies the group
     * @result array of groupes
     */
    protected function _aggregate_rows($rows, array $aggregate_fields, $group_by_column)
    {
        $grouped = array();

        foreach ($rows as $row)
        {
            $key = $row[$group_by_column];

            if (isset($grouped[$key]))
            {
                foreach ($aggregate_fields as $field)
                    $grouped[$key][$field][] = $row[$field];
            }
            else
            {
                $grouped[$key] = $row;

                foreach ($aggregate_fields as $field)
                    $grouped[$key][$field] = array($row[$field]);
            }
        }

        return array_values($grouped);
    }

    /** @author Pieter de Bie
      * Get members by searching in their first OR last names.
      * Only a part of the name needs to be matched.
      * @first a part of the first name to search for
      * @last a part of the last name to search for
      *
      * @result an array of #DataIter
      */
    public function search_name($name, $limit = null)
    {
        if (!$name)
            return array();

        $name = $this->db->escape_string($name);

        $query = "SELECT
                leden.*,
                COUNT(DISTINCT foto_faces.id) as number_of_tags,
                COUNT(DISTINCT committee_members.committee_id) number_of_committees
                FROM
                    leden
                LEFT JOIN committee_members ON
                    committee_members.member_id = leden.id
                LEFT JOIN foto_faces ON
                    foto_faces.lid_id = leden.id
                WHERE
                    unaccent(lower(CASE
                        WHEN coalesce(tussenvoegsel, '') = '' THEN
                            voornaam || ' ' || achternaam
                        ELSE
                            voornaam || ' ' || tussenvoegsel || ' ' || achternaam
                    END)) ILIKE unaccent('%{$name}%')
                    OR unaccent(leden.nick) ILIKE unaccent('%{$name}%')
                GROUP BY
                    leden.id
                ORDER BY
                    number_of_tags DESC,
                    number_of_committees DESC,
                    leden.voornaam ASC";

        if ($limit !== null)
            $query .= sprintf(' LIMIT %d', $limit);

        $rows = $this->db->query($query);

        $members = $this->_rows_to_iters($rows);

        // Filter out people who don't show their name
        // Except when you are the board! The board can do anything!
        // All hail the Board!
        // Hail AC/DCee too!
        if (!$this->auth->identity->member_in_committee(COMMISSIE_BESTUUR)
            && !$this->auth->identity->member_in_committee(COMMISSIE_KANDIBESTUUR))
            $members = array_filter($members, function($member) {
                return !$this->is_private($member, 'naam');
            });

        //'rebase' the array so PHP doesn't forget to count properly starting from zero ;)
        return array_values($members);
    }

    public function search(string $query, ?int $limit = null): array
    {
        return $this->search_name($query, $limit);
    }

    /**
      * Get members by searching for their starting year
      *
      * @result an array of #DataIter
      */
    public function get_from_search_year($year)
    {
        $rows = $this->db->query("SELECT *
                FROM leden
                WHERE beginjaar = " . intval($year) . "
                ORDER BY achternaam");

        return $this->_rows_to_iters($rows);
    }

    /**
      * Get all years that have active members
      *
      * @result an array of active years
      */
    public function get_distinct_years($all=false)
    {
        if (
            $all
            || $this->auth->identity->member_in_committee(COMMISSIE_BESTUUR)
            || $this->auth->identity->member_in_committee(COMMISSIE_KANDIBESTUUR)
        )
            $query = "SELECT DISTINCT beginjaar
                    FROM leden
                    ORDER BY beginjaar ASC";
        else
            $query = "SELECT DISTINCT beginjaar
                    FROM leden
                    WHERE donor_from < NOW() AND (donor_till IS NULL OR donor_till > NOW())
                       OR member_from < NOW() AND (member_till IS NULL OR member_till > NOW())
                    ORDER BY beginjaar ASC";

        $rows = $this->db->query($query);

        $years = [];
        foreach ($rows as $row)
            $years[] = $row['beginjaar'];

        return $years;
    }

    public function get_from_status($status)
    {
        switch($status) {
            case self::STATUS_LID:
                $condition = "member_from < NOW() AND (member_till IS NULL OR member_till > NOW())";
                break;

            case self::STATUS_DONATEUR:
                $condition = "donor_from < NOW() AND (donor_till IS NULL OR donor_till > NOW())";
                break;

            case self::STATUS_LID_AF:
                $condition = "member_from < NOW()";
                break;

            case self::STATUS_PENDING:
                $condition = "member_from IS NULL AND donor_from IS NULL";
                break;

            default:
                throw new \InvalidArgumentException('DataModelMember::get_from_status() does not support this status');
        }

        $rows = $this->db->query("SELECT *
                FROM leden
                WHERE $condition
                ORDER BY voornaam");

        return $this->_rows_to_iters($rows);
    }

    /**
      * Check if a member already exists
      * @memberid the member id
      *
      * @result true if the member id is already used, false
      * otherwise
      */
    public function exists($memberid)
    {
        $val = $this->db->query_value('SELECT 1
                FROM leden
                WHERE id = ' . intval($memberid));

        return ($val == 1);
    }

    public function get_profile_picture(DataIterMember $iter)
    {
        return $this->profilePictureModel->get_for_member($iter);
    }
}
