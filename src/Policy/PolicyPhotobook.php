<?php

namespace App\Policy;

use App\DataIter\DataIterPhotobook;
use App\DataIter\DataIterFacesPhotobook;
use App\DataIter\DataIterRootPhotobook;
use App\DataModel\DataModelCommissie;
use App\DataModel\DataModelPhotobook;
use App\Legacy\Authentication\Authentication;
use App\Legacy\Authentication\IdentityProviderInterface;
use App\Legacy\Database\DataIter;
use App\Legacy\Policy\PolicyInterface;
use App\Policy\PolicyMember;

class PolicyPhotobook implements PolicyInterface
{
    protected IdentityProviderInterface $identity;

    public static function getSupportedModel(): string
    {
        return DataModelPhotobook::class;
    }

    public function __construct(
        protected Authentication $auth,
        protected PolicyMember $policyMember,
    ) {
        $this->identity = $auth->getIdentity();
    }

    private function _wasMemberAtTheTime(DataIter $book) {
        if ($this->identity->member() === null)
            return false;

        if ($book['date'] === null)
            return false;

        if (!preg_match('/^(?P<year>\d{4})-\d{1,2}-\d{1,2}$/', $book['date'], $match))
            return false;

        $book_date = new \DateTime($book['date']);
        $book_year_end = intval($book_date->format('Y'));
        if (intval($book_date->format('n')) >= 8)
            $book_year_end++;

        /* A person should be able to see this book if:
         * - they were member at the time of the book.
         * - they were member at the "end" of the academic year the book was created in.
         * The end for academic year is set to the first of August, as some members join before
         * September, and sometimes the first activities of the year are still in August.
         */
        return $this->identity->member()->is_member_on($book_date)
            || $this->identity->member()->is_member_on(new \DateTime($book_year_end . '-08-01'));
    }

    private function _insidePublicPeriod(DataIter $book)
    {
        if ($book['date'] === null)
            return false;

        if (!preg_match('/^(?P<year>\d{4})-\d{1,2}-\d{1,2}$/', $book['date'], $match))
            return false;

        return intval($match['year']) >= intval(date("Y", strtotime("-2 year")));
    }

    public function userCanCreate(DataIter $book): bool
    {
        return $this->identity->member_in_committee(DataModelCommissie::PHOTOCEE)
            && ctype_digit((string) $book['parent_id']); // no generated photobook
    }

    public function userCanRead(DataIter $book): bool
    {
        // First: if the access to the photo book is of a higher level
        // than the current user has, no way he/she can view the photo
        // book.
        if ($book['visibility'] !== null && $this->getAccessLevel() < $book->get('visibility'))
            return false;

        // Member-specific albums are also forbidden terrain unless they are about you
        if (!$this->identity->is_member() && $book instanceof DataIterFacesPhotobook)
            return $book['member_ids'] == [$this->identity->get('id')];

        // Member-specific albums are forbidden if one of the members has marked their photo* as hidden
        // or if their whole profile has been made inaccessible
        if ($book instanceof DataIterFacesPhotobook && !$this->identity->member_in_committee(DataModelCommissie::BOARD))
            foreach ($book['members'] as $member)
                if (!$this->policyMember->userCanRead($member) || $member->is_private('foto', true))
                    return false;

        // Older photo books are not visible for non-members
        if ($this->identity->is_member() || $this->identity->is_device())
            return true;

        if ($book['date'] === null)
            return true;

        if ($this->_wasMemberAtTheTime($book))
            return true;

        if ($this->_insidePublicPeriod($book))
            return true;

        return false;
    }

    public function userCanUpdate(DataIter $book): bool
    {
        return $this->identity->member_in_committee(DataModelCommissie::PHOTOCEE)
            && ctype_digit((string) $book->get_id()) // test whether this isn't a special book, such as the Favorites or Faces albums which are generated
            && $book->get_id() > 0;
    }

    public function userCanDelete(DataIter $book): bool
    {
        return $this->userCanUpdate($book);
    }

    public function userCanDownloadBook(DataIterPhotobook $book): bool
    {
        if ($book instanceof DataIterRootPhotobook)
            return false;

        if (!$this->identity->member())
            return false;

        if ($this->identity->is_member() || $this->_wasMemberAtTheTime($book))
            return $this->userCanRead($book);

        return false;
    }

    public function userCanMarkAsRead(DataIterPhotobook $book): bool
    {
        return // only logged in members can track their viewed photo books
            $this->auth->loggedIn

            // and only if we actually are watching a book
            && $book->get_id()

            // which is not artificial (faces, likes)
            && ctype_digit((string) $book->get_id())

            // and has photos
            && $book['num_books'] > 0;
    }

    public function getAccessLevel()
    {
        if ($this->identity->member_in_committee(DataModelCommissie::PHOTOCEE))
            return DataModelPhotobook::VISIBILITY_PHOTOCEE;

        if ($this->identity->member_in_committee())
            return DataModelPhotobook::VISIBILITY_ACTIVE_MEMBERS;

        if ($this->identity->is_member() || $this->identity->is_device())
            return DataModelPhotobook::VISIBILITY_MEMBERS;

        else // Donors are also treated as PUBLIC
            return DataModelPhotobook::VISIBILITY_PUBLIC;
    }
}
