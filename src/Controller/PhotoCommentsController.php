<?php

namespace App\Controller;

use App\DataIter\DataIterPhoto;
use App\DataIter\DataIterPhotobook;
use App\DataModel\DataModelPhotobook;
use App\DataModel\DataModelPhotobookReactie;
use App\Exception\UnauthorizedException;
use App\Form\PhotoCommentType;
use App\Service\Authentication;
use App\Service\Policy;
use App\Utils\PhotoBookUtils;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/photos/{book_id}/photo/{photo_id<\d+>}/comments', requirements: ['book_id' => '\d+|liked|member(_\d+)+'])]
class PhotoCommentsController extends AbstractController
{
    private DataIterPhotobook $book;
    private DataIterPhoto $photo;

    public function __construct(
        private DataModelPhotobook $bookModel,
        private DataModelPhotobookReactie $model,
        private Policy $policy,
        private PhotoBookUtils $photoBookUtils,
    ) {
    }

    private function validateRoute(Request $request): ?RedirectResponse
    {
        $photo_id = $request->attributes->get('_route_params')['photo_id'];
        $this->photo = $this->bookModel->get_iter($photo_id);

        $book_id = $request->attributes->get('_route_params')['book_id'];
        $this->book = $this->photoBookUtils->getBook($book_id);

        if (!$this->book->has_photo($this->photo))
            // Illegal situation! Redirect to a legal route.
            return $this->redirectToRoute(
                $request->attributes->get('_route'),
                array_merge(
                    $request->attributes->get('_route_params'),
                    [
                        'book_id' => $this->photo['boek'],
                        'photo_id' => $this->photo->get_id(),
                    ]
                ),
            );

        return null;
    }

    /**
     * Render the "_photo" fragment
     */
    public function photo(Authentication $auth, DataIterPhoto $photo, DataIterPhotobook $book)
    {
        $iter = $this->model->new_iter([
            'foto' => $photo->get_id(),
            'auteur' => $auth->identity->get('id', null),
        ]);

        $form = $this->createForm(PhotoCommentType::class, $iter, [
            'mapped' => false,
            'csrf_token_id' => sprintf('photo_comment_%s_%s', ($iter['foto'] ?? ''), ($iter->get_id() ?? '')),
        ]);

        return $this->render('photos/comments/_photo.html.twig', [
            'comments' => $photo->get_comments(),
            'book' => $book,
            'photo' => $photo,
            'new_comment' => $iter,
            'form' => $form,
        ]);
    }

    #[Route('/create', name: 'photo_comments.create', methods: ['GET', 'POST'])]
    public function create(
        Authentication $auth,
        Request $request,
    ): Response|RedirectResponse
    {
        if ($response = $this->validateRoute($request))
            return $response;

        $iter = $this->model->new_iter([
            'foto' => $this->photo->get_id(),
            'auteur' => $auth->identity->get('id'),
        ]);

        if (!$this->policy->userCanCreate($iter))
            throw new UnauthorizedException('You are not allowed to comment.');

        $form = $this->createForm(PhotoCommentType::class, $iter, [
            'mapped' => false,
            'csrf_token_id' => sprintf('photo_comment_%s_%s', ($iter['foto'] ?? ''), ($iter->get_id() ?? '')),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $id = $this->model->insert($iter);
            return $this->redirectToRoute('photos.single', [
                'book_id' => $this->book->get_id(),
                'photo_id' => $this->photo->get_id(),
                // '_fragment' => 'comment_' . $id,
            ]);
        }

        return $this->render('photos/comments/form.html.twig', [
            'iter' => $iter,
            'book' => $this->book,
            'photo' => $this->photo,
            'form' => $form,
        ]);
    }

    #[Route('/{id<\d+>}/update', name: 'photo_comments.update', methods: ['GET', 'POST'])]
    public function update(Request $request, int $id): Response|RedirectResponse
    {
        if ($response = $this->validateRoute($request))
            return $response;

        $iter = $this->model->get_iter($id);

        if (!$this->policy->userCanUpdate($iter))
            throw new UnauthorizedException('You are not allowed to edit this comment.');

        $form = $this->createForm(PhotoCommentType::class, $iter, [
            'mapped' => false,
            'csrf_token_id' => sprintf('photo_comment_%s_%s', ($iter['foto'] ?? ''), ($iter->get_id() ?? '')),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->model->update($iter);
            return $this->redirectToRoute('photos.single', [
                'book_id' => $this->book->get_id(),
                'photo_id' => $this->photo->get_id(),
                // '_fragment' => 'comment_' . $id,
            ]);
        }

        return $this->render('photos/comments/form.html.twig', [
            'iter' => $iter,
            'book' => $this->book,
            'photo' => $this->photo,
            'form' => $form,
        ]);
    }

    #[Route('/{id<\d+>}/delete', name: 'photo_comments.delete', methods: ['GET', 'POST'])]
    public function delete(Request $request, int $id): Response|RedirectResponse
    {
        if ($response = $this->validateRoute($request))
            return $response;

        $iter = $this->model->get_iter($id);

        if (!$this->policy->userCanDelete($iter))
            throw new UnauthorizedException('You are not allowed to delete this comment.');

        $form = $this->createFormBuilder($iter)
            ->add('submit', SubmitType::class, ['label' => __('Delete'), 'color' => 'danger'])
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->model->delete($iter);
            return $this->redirectToRoute('photos.single', [
                'book_id' => $this->book->get_id(),
                'photo_id' => $this->photo->get_id(),
            ]);
        }

        return $this->render('photos/comments/confirm_delete.html.twig', [
            'iter' => $iter,
            'book' => $this->book,
            'photo' => $this->photo,
            'form' => $form,
        ]);
    }

    #[Route('/{id<\d+>}/likes', name: 'photo_comments.likes', methods: ['GET', 'POST'])]
    public function likes(Authentication $auth, Request $request, int $id): Response|RedirectResponse
    {
        if ($response = $this->validateRoute($request))
            return $response;

        $iter = $this->model->get_iter($id);

        if (!$this->policy->userCanLike($iter))
            throw new UnauthorizedException('You are not allowed to like this comment.');

        $form = $this->createFormBuilder(null, ['csrf_token_id' => 'like_photo_comment_' . $iter->get_id()])
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
                    $iter->like($auth->identity->member());
                elseif ($action === 'unlike')
                    $iter->unlike($auth->identity->member());
            } catch (\Exception $e) {
                // Don't break duplicate requests
            }
        }

        if ($request->getContentTypeFormat() === 'json')
            return $this->json([
                'liked' => $auth->loggedIn && $iter->is_liked_by($auth->identity->member()),
                'likes' => $iter->get_likes(),
            ]);

        return $this->redirectToRoute('photos.single', [
            'book_id' => $this->book->get_id(),
            'photo_id' => $this->photo->get_id(),
        ]);
    }
}
