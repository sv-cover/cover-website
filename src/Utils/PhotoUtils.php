<?php

namespace App\Utils;

use App\Service\Authentication;
use App\Service\Database;

final class PhotoUtils
{
    private $book;

    public function __construct(
        private Authentication $auth,
        private Database $db,
    ) {
    }

    public function getBook(int|string $bookId = null, \DataIterPhoto $photo = null): \DataIterPhotobook
    {
        if (isset($this->book))
            return $this->book;

        if (!empty($bookId) && ctype_digit($bookId) && intval($bookId) > 0) {
            // Single book page
            $book = $this->db->getModel('DataModelPhotobook')->get_book($bookId);
        } elseif (!empty($bookId) && $bookId == 'liked') {
            // Book with the current user's likes
            $book = $this->db->getModel('DataModelPhotobookLike')->get_book($this->auth->identity->member());
        } elseif (!empty($bookId) && preg_match('/^member_(\d+(?:_\d+)*)$/', $bookId, $match)) {
            // Book with photos in which a certain member
            $members = [];

            foreach (explode('_', $match[1]) as $memberId)
                $members[] = $this->db->getModel('DataModelMember')->get_iter($memberId);

            $book = $this->db->getModel('DataModelPhotobookFace')->get_book($members);
        } elseif ($photo) {
            // If there is a photo, then use the book of that one
            $book = $photo->get_book();
        } else {
            // Only one option left: the root book
            $book = $this->db->getModel('DataModelPhotobook')->get_root_book();
        }

        $this->book = $book;
        return $book;
    }

    public function getBookThumbnails(\DataIterPhotobook $book, int $count): array
    {
        return $this->db->getModel('DataModelPhotobook')->get_photos_recursive($book, $count, true, 0.69);
    }
}
