<?php

namespace App\DataModel;

use App\DataIter\DataIterPhoto;
use App\DataIter\DataIterPhotobook;
use App\DataIter\DataIterPhotoSubmission;
use App\Legacy\Database\DataModel;

class DataModelPhotoSubmission extends DataModel
{
    public string $dataiter = DataIterPhotoSubmission::class;
    public string $table = 'foto_submissions';

    public function get_pending_for_book(DataIterPhotobook $book): array
    {
        $rows = $this->db->query(sprintf(
            "SELECT * FROM foto_submissions WHERE boek = %d AND status = 'pending' ORDER BY submitted_on ASC",
            $book->get_id()
        ));
        return $this->_rows_to_iters($rows);
    }

    public function count_pending_for_book(int $bookId): int
    {
        $row = $this->db->query_first(sprintf(
            "SELECT COUNT(*) as cnt FROM foto_submissions WHERE boek = %d AND status = 'pending'",
            $bookId
        ));
        return (int) ($row['cnt'] ?? 0);
    }

    public function get_all_pending(): array
    {
        $rows = $this->db->query(
            "SELECT * FROM foto_submissions WHERE status = 'pending' ORDER BY submitted_on ASC"
        );
        return $this->_rows_to_iters($rows);
    }

    public function approve(DataIterPhotoSubmission $sub, DataModelPhotobook $pbModel, string $photosDir, int $reviewerId): DataIterPhoto
    {
        $ext = pathinfo($sub->get('filepath'), PATHINFO_EXTENSION);
        $permanentRelPath = sprintf('submissions/approved/%d.%s', $sub->get_id(), $ext);
        $permanentAbsPath = rtrim($photosDir, '/') . '/' . $permanentRelPath;

        $approvedDir = dirname($permanentAbsPath);
        if (!is_dir($approvedDir))
            mkdir($approvedDir, 0775, true);

        $sourcePath = rtrim($photosDir, '/') . '/' . $sub->get('filepath');
        rename($sourcePath, $permanentAbsPath);

        try {
            $photo = new DataIterPhoto($pbModel, -1, [
                'boek'        => $sub->get('boek'),
                'beschrijving' => $sub->get('beschrijving'),
                'filepath'    => $permanentRelPath,
            ]);

            $photoId = $pbModel->insert($photo);
            $photo->set_id($photoId);

            $book = new DataIterPhotobook($pbModel, $sub->get('boek'), []);
            $book['last_update'] = new \DateTime();
            $pbModel->update_book($book);

            $sub->set('status', 'approved');
            $sub->set('reviewed_by', $reviewerId);
            $sub->set('reviewed_on', (new \DateTime())->format('Y-m-d H:i:sP'));
            $this->update($sub);
        } catch (\Throwable $e) {
            rename($permanentAbsPath, $sourcePath);
            throw $e;
        }

        return $photo;
    }

    public function reject(DataIterPhotoSubmission $sub, string $photosDir, int $reviewerId): void
    {
        $absPath = rtrim($photosDir, '/') . '/' . $sub->get('filepath');
        if (file_exists($absPath))
            unlink($absPath);

        $sub->set('status', 'rejected');
        $sub->set('reviewed_by', $reviewerId);
        $sub->set('reviewed_on', (new \DateTime())->format('Y-m-d H:i:sP'));
        $this->update($sub);
    }
}
