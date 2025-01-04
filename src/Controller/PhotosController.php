<?php

namespace App\Controller;

use App\DataModel\DataModelPhotobook;
use App\DataModel\DataModelPhotobookLike;
use App\DataModel\DataModelPhotobookPrivacy;
use App\Exception\NotFoundException;
use App\Exception\UnauthorizedException;
use App\Form\PhotoType;
use App\Legacy\Authentication\Authentication;
use App\Legacy\Policy\Policy;
use App\Utils\PhotoBookUtils;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/photos', requirements: ['book_id' => '\d+|liked|member(_\d+)+'])]
class PhotosController extends AbstractController
{
    const MAX_SIZE = 2400;

    public function __construct(
        private DataModelPhotobook $model,
        private Policy $policy,
        private PhotoBookUtils $photoBookUtils,
    ) {
    }

    #[Route('/photo/{photo_id<\d+>}', methods: ['GET'])] // Courtesy route, rarely (if ever) used.
    #[Route('/{book_id}/photo/{photo_id<\d+>}', name: 'photos.single', methods: ['GET'])]
    public function single(
        Authentication $auth,
        DataModelPhotobookLike $likeModel,
        int $photo_id,
        ?string $book_id = null
    ): Response|RedirectResponse
    {
        $photo = $this->model->get_iter($photo_id);
        $book = $this->photoBookUtils->getBook($book_id);

        if (!$book->has_photo($photo))
            // Illegal situation! Redirect to a legal route.
            return $this->redirectToRoute('photos.single', [
                'book_id' => $photo['boek'],
                'photo_id' => $photo->get_id(),
            ]);

        if (!$this->policy->userCanRead($photo))
            throw new UnauthorizedException('You are not allowed to see this photo.');

        return $this->render('photos/single.html.twig', [
            'book' => $book,
            'photo' => $photo,
            'is_liked' => $auth->loggedIn && $likeModel->is_liked($photo, $auth->identity->get('id')),
        ]);
    }

    #[Route('/{book_id}/photo/{photo_id<\d+>}/update', name: 'photos.update', methods: ['GET', 'POST'])]
    public function update(Request $request, int $photo_id, string $book_id): Response|RedirectResponse
    {
        $photo = $this->model->get_iter($photo_id);
        $book = $this->photoBookUtils->getBook($book_id);

        if (!$book->has_photo($photo))
            // Illegal situation! Redirect to a legal route.
            return $this->redirectToRoute('photos.update', [
                'book_id' => $photo['boek'],
                'photo_id' => $photo->get_id(),
            ]);

        if (!$this->policy->userCanUpdate($photo))
            throw new UnauthorizedException('You are not allowed to edit this photo.');

        $form = $this->createForm(PhotoType::class, $photo, ['mapped' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $photo->set('beschrijving', $form['beschrijving']->getData());
            $this->model->update($photo);
            return $this->redirectToRoute('photos.single', [
                'book_id' => $book->get_id(),
                'photo_id' => $photo->get_id(),
            ]);
        }

        return $this->render('photos/form.html.twig',  [
            'book' => $book,
            'photo' => $photo,
            'form' => $form,
        ]);
    }

    #[Route('/{book_id}/photo/{photo_id<\d+>}/likes', name: 'photos.likes', methods: ['GET', 'POST'])]
    public function likes(
        Authentication $auth,
        DataModelPhotobookLike $likeModel,
        Request $request,
        int $photo_id,
        string $book_id,
    ): Response|RedirectResponse
    {
        $photo = $this->model->get_iter($photo_id);
        $book = $this->photoBookUtils->getBook($book_id);

        if (!$book->has_photo($photo))
            // Illegal situation! Redirect to a legal route.
            return $this->redirectToRoute('photos.likes', [
                'book_id' => $photo['boek'],
                'photo_id' => $photo->get_id(),
            ]);

        if (!$this->policy->userCanLike($photo))
            throw new UnauthorizedException('You are not allowed to like this photo.');

        $form = $this->createFormBuilder(null, ['csrf_token_id' => 'like_photo_' . $photo->get_id()])
            ->add('like', SubmitType::class)
            ->add('unlike', SubmitType::class)
            ->getForm();
        $form->handleRequest($request);

        $action = null;

        if ($request->getContentTypeFormat() === 'json') {
            $data = $request->toArray();
            $action = $data['action'] ?? null;
        } elseif ($form->isSubmitted() && $form->isValid()) {
            $action = $form->get('like')->isClicked() ? 'like' : 'unlike';
        }

        if (isset($action)) {
            try {
                if ($action === 'like')
                    $likeModel->like($photo, $auth->identity->get('id'));
                elseif ($action === 'unlike')
                    $likeModel->unlike($photo, $auth->identity->get('id'));
            } catch (\Exception $e) {
                // Don't break duplicate requests
            }
        }

        if ($request->getContentTypeFormat() === 'json')
            return $this->json([
                'liked' => $auth->loggedIn && $likeModel->is_liked($photo, $auth->identity->get('id')),
                'likes' => count($photo->get_likes()),
            ]);

        return $this->redirectToRoute('photos.single', [
            'book_id' => $book->get_id(),
            'photo_id' => $photo->get_id(),
        ]);
    }

    #[Route('/{book_id}/photo/{photo_id<\d+>}/privacy', name: 'photos.privacy', methods: ['GET', 'POST'])]
    public function privacy(
        Authentication $auth,
        DataModelPhotobookPrivacy $privacyModel,
        Request $request,
        int  $photo_id,
        string $book_id,
    ): Response|RedirectResponse
    {
        $photo = $this->model->get_iter($photo_id);
        $book = $this->photoBookUtils->getBook($book_id);

        if (!$book->has_photo($photo))
            // Illegal situation! Redirect to a legal route.
            return $this->redirectToRoute('photos.privacy', [
                'book_id' => $photo['boek'],
                'photo_id' => $photo->get_id(),
            ]);

        if (!$this->policy->userCanSetPrivacy($photo))
            throw new UnauthorizedException('You are not allowed to change the visibility of your tag.');

        $member = $auth->identity->member();

        $data = [
            'visibility' => $privacyModel->is_visible($photo, $member) ? 'visible' : 'hidden',
        ];

        $form = $this->createFormBuilder($data)
            ->add('visibility', ChoiceType::class, [
                'label' => __('Visibility of this photo'),
                'choices'  => [
                    __('Show photo in my personal photo album') => 'visible',
                    __('Hide from my personal photo album') => 'hidden',
                ],
                'expanded' => true,
            ])
            ->add('submit', SubmitType::class, ['label' => __('Change visibility')])
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($form['visibility']->getData() == 'hidden')
                $privacyModel->mark_hidden($photo, $member);
            else
                $privacyModel->mark_visible($photo, $member);

            return $this->redirectToRoute('photos.single', [
                'book_id' => $book->get_id(),
                'photo_id' => $photo->get_id(),
            ]);
        }

        return $this->render('photos/privacy.html.twig', [
            'book' => $book,
            'photo' => $photo,
            'form' => $form,
        ]);
    }

    #[Route('/photos/{photo_id<\d+>}/original', name: 'photos.original', methods: ['GET'])]
    public function original(int $photo_id): BinaryFileResponse|RedirectResponse
    {
        $photo = $this->model->get_iter($photo_id);

        if (!$this->policy->userCanDownload($photo) || !$this->policy->userCanRead($photo))
            throw new UnauthorizedException('You may need to login to download this photo.');

        if (!$photo->file_exists())
            throw $this->createNotFoundException('Could not find original file');

        $file = new File($photo->get_full_path());

        return $this->file($file);
    }

    #[Route('/photos/{photo_id<\d+>}/scaled/{width<\d+>}/{height<\d+>}', name: 'photos.scaled', methods: ['GET'])]
    public function scaled(
        int $photo_id,
        ?int $width = null,
        ?int $height = null,
        #[MapQueryParameter] bool $skipCache = false,
    ): Response|RedirectResponse
    {
        $photo = $this->model->get_iter($photo_id);

        if (!$this->policy->userCanRead($photo))
            throw new UnauthorizedException('You may need to login to view this photo.');

        $width = !empty($width) ? min($width, self::MAX_SIZE) : null;
        $height = !empty($height) ? min($height, self::MAX_SIZE) : null;

        $cacheStatus = null;

        try {
            $filePath = $photo->get_resource($width, $height, $skipCache, $cacheStatus);
        } catch (NotFoundException $e) {
            // Allow fallback only in debug mode.
            if ($this->getParameter('kernel.debug') && $this->getParameter('app.photos_scaled_url')) {
                return $this->redirect(strtr(
                    $this->getParameter('app.photos_scaled_url'),
                    [
                        '{photo_id}' => $photo_id,
                        '{width}' => $width ?? 0,
                        '{height}' => $height ?? 0,
                    ]
                ));
            } else {
                throw $e;
            }
        }

        $lastModified = gmdate(DATE_RFC1123,filemtime($filePath));

        $file = new File($filePath);

        $response = $this->file($file);

        $cacheExpires = 180*24*3600;
        $response->setPublic();
        $response->setMaxAge($cacheExpires);
        $response->headers->set('X-Cache-Status', $cacheStatus);
        $response->headers->remove('Content-Disposition');

        return $response;
    }
}
