<?php

namespace App\Controller;

use App\DataIter\DataIterPhotoSubmission;
use App\DataModel\DataModelCommissie;
use App\DataModel\DataModelPhotobook;
use App\DataModel\DataModelPhotoSubmission;
use App\Exception\UnauthorizedException;
use App\Form\PhotoSubmissionType;
use App\Legacy\Authentication\Authentication;
use App\Legacy\Policy\Policy;
use App\Utils\PhotoBookUtils;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/photos', requirements: ['book_id' => '\d+|liked|member(_\d+)+'])]
class PhotoSubmissionsController extends AbstractController
{
    public function __construct(
        private DataModelPhotobook $photobookModel,
        private DataModelPhotoSubmission $submissionModel,
        private Policy $policy,
        private PhotoBookUtils $photoBookUtils,
        private Authentication $auth,
    ) {
    }

    #[Route('/{book_id}/submit_photos', name: 'photo_submissions.submit', methods: ['GET', 'POST'])]
    public function submit(Request $request, string $book_id): Response|RedirectResponse
    {
        $book = $this->photoBookUtils->getBook($book_id);

        if (!$this->policy->userCanSubmitPhotos($book))
            throw new UnauthorizedException();

        $form = $this->createForm(PhotoSubmissionType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $uploadedFiles = $form->get('photo')->getData() ?? [];
            $beschrijving = (string) ($form->get('beschrijving')->getData() ?? '');

            $photosDir = $this->getParameter('app.photos_dir');
            $submissionsDir = rtrim($photosDir, '/') . '/submissions';
            if (!is_dir($submissionsDir))
                mkdir($submissionsDir, 0775, true);

            $identity = $this->auth->getIdentity();
            $isPrivileged = $identity->member_in_committee(DataModelCommissie::PHOTOCEE)
                || $identity->member_in_committee(DataModelCommissie::BOARD);

            foreach ($uploadedFiles as $uploadedFile) {
                $ext = strtolower($uploadedFile->guessExtension() ?? 'jpg');
                if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif']))
                    $ext = 'jpg';

                $filename = uniqid('sub_', true) . '.' . $ext;
                $uploadedFile->move($submissionsDir, $filename);

                $sub = new DataIterPhotoSubmission($this->submissionModel, null, [
                    'boek'         => $book->get_id(),
                    'uploaded_by'  => $this->auth->identity->get('id'),
                    'filepath'     => 'submissions/' . $filename,
                    'beschrijving' => $beschrijving,
                    'status'       => 'pending',
                ]);

                $subId = $this->submissionModel->insert($sub);
                $sub->set_id($subId);

                if ($isPrivileged)
                    $this->submissionModel->approve($sub, $this->photobookModel, $photosDir, $identity->get('id'));
            }

            $count = count($uploadedFiles);
            $this->addFlash('success', $isPrivileged
                ? __N('Your photo has been added to the album.', '%d photos have been added to the album.', $count)
                : __N('Your photo has been submitted and is awaiting approval.', '%d photos have been submitted and are awaiting approval.', $count)
            );

            return $this->redirectToRoute('photo_books.single', ['book_id' => $book->get_id()]);
        }

        return $this->render('photos/submissions/submit.html.twig', [
            'book' => $book,
            'form' => $form,
        ]);
    }

    #[Route('/{book_id}/review_submissions', name: 'photo_submissions.review', methods: ['GET'])]
    public function review(string $book_id): Response
    {
        $book = $this->photoBookUtils->getBook($book_id);

        if (!$this->policy->userCanReviewSubmissions($book))
            throw new UnauthorizedException();

        $submissions = $this->submissionModel->get_pending_for_book($book);

        return $this->render('photos/submissions/review.html.twig', [
            'book'        => $book,
            'submissions' => $submissions,
        ]);
    }

    #[Route('/submissions/{id}/approve', name: 'photo_submissions.approve', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function approveSubmission(Request $request, int $id): RedirectResponse
    {
        $sub = $this->submissionModel->get_iter($id);

        $book = $this->photoBookUtils->getBook((string) $sub->get('boek'));

        if (!$this->policy->userCanReviewSubmissions($book))
            throw new UnauthorizedException();

        $form = $this->createFormBuilder(null, ['csrf_token_id' => 'approve_submission_' . $id])
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $photosDir = $this->getParameter('app.photos_dir');
            $this->submissionModel->approve(
                $sub,
                $this->photobookModel,
                $photosDir,
                $this->auth->identity->get('id')
            );
            $this->addFlash('success', __('Photo approved and added to the album.'));
        }

        return $this->redirectToRoute('photo_submissions.review', ['book_id' => $sub->get('boek')]);
    }

    #[Route('/submissions/{id}/thumbnail', name: 'photo_submissions.thumbnail', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function thumbnail(int $id): BinaryFileResponse
    {
        $sub = $this->submissionModel->get_iter($id);

        if (!$this->policy->userCanReviewSubmissions(
            $this->photoBookUtils->getBook((string) $sub->get('boek'))
        ))
            throw new UnauthorizedException();

        $photosDir = $this->getParameter('app.photos_dir');
        $absPath = rtrim($photosDir, '/') . '/' . $sub->get('filepath');

        if (!file_exists($absPath))
            throw new NotFoundHttpException();

        return new BinaryFileResponse($absPath);
    }

    #[Route('/submissions/{id}/reject', name: 'photo_submissions.reject', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function rejectSubmission(Request $request, int $id): RedirectResponse
    {
        $sub = $this->submissionModel->get_iter($id);

        $book = $this->photoBookUtils->getBook((string) $sub->get('boek'));

        if (!$this->policy->userCanReviewSubmissions($book))
            throw new UnauthorizedException();

        $form = $this->createFormBuilder(null, ['csrf_token_id' => 'reject_submission_' . $id])
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $photosDir = $this->getParameter('app.photos_dir');
            $this->submissionModel->reject($sub, $photosDir, $this->auth->identity->get('id'));
            $this->addFlash('success', __('Photo submission rejected.'));
        }

        return $this->redirectToRoute('photo_submissions.review', ['book_id' => $sub->get('boek')]);
    }
}
