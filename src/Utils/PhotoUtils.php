<?php

namespace App\Utils;

use App\DataIter\DataIterPhoto;
use App\DataIter\DataIterPhotobook;
use App\DataModel\DataModelMember;
use App\DataModel\DataModelPhotobook;
use App\DataModel\DataModelPhotobookLike;
use App\DataModel\DataModelPhotobookFace;
use App\Service\Authentication;

final class PhotoUtils
{
    private $book;

    public function __construct(
        private Authentication $auth,
        private DataModelMember $memberModel,
        private DataModelPhotobook $bookModel,
        private DataModelPhotobookLike $likeModel,
        private DataModelPhotobookFace $faceModel,
    ) {
    }

    public function getBook(int|string $bookId = null, DataIterPhoto $photo = null): DataIterPhotobook
    {
        if (isset($this->book))
            return $this->book;

        if (!empty($bookId) && ctype_digit($bookId) && intval($bookId) > 0) {
            // Single book page
            $book = $this->bookModel->get_book($bookId);
        } elseif (!empty($bookId) && $bookId == 'liked') {
            // Book with the current user's likes
            $book = $this->likeModel->get_book($this->auth->identity->member());
        } elseif (!empty($bookId) && preg_match('/^member_(\d+(?:_\d+)*)$/', $bookId, $match)) {
            // Book with photos in which a certain member
            $members = [];

            foreach (explode('_', $match[1]) as $memberId)
                $members[] = $this->memberModel->get_iter($memberId);

            $book = $this->faceModel->get_book($members);
        } elseif ($photo) {
            // If there is a photo, then use the book of that one
            $book = $photo->get_book();
        } else {
            // Only one option left: the root book
            $book = $this->bookModel->get_root_book();
        }

        $this->book = $book;
        return $book;
    }

    public function getBookThumbnails(DataIterPhotobook $book, int $count): array
    {
        return $this->bookModel->get_photos_recursive($book, $count, true, 0.69);
    }
}
