<?php

namespace App\Controller;

use App\DataModel\DataModelPoll;
use App\DataModel\DataModelPollComment;
use App\DataModel\DataModelPollCommentLike;
use App\Exception\UnauthorizedException;
use App\Form\PollCommentType;
use App\Service\Authentication;
use App\Service\Policy;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/polls/{poll_id<\d+>}/comments')]
class PollCommentsController extends AbstractController
{
    public function __construct(
        private DataModelPollComment $commentModel,
        private DataModelPoll $pollModel,
        private Policy $policy,
    ) {
    }

    #[Route('/create', name: 'poll_comments.create', methods: ['GET', 'POST'])]
    public function create(Authentication $auth, Request $request, int $poll_id): Response|RedirectResponse
    {
        $poll = $this->pollModel->get_iter($poll_id);
        $iter = $this->commentModel->new_iter([
            'poll_id' => $poll->get_id(),
            'member_id' => $auth->identity->get('id'),
        ]);

        if (!$this->policy->userCanCreate($iter))
            throw new UnauthorizedException('You are not allowed to comment on this poll.');

        $form = $this->createForm(PollCommentType::class, $iter, [
            'mapped' => false,
            'csrf_token_id' => sprintf('poll_%s_comment', $poll->get_id()),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $id = $this->commentModel->insert($iter);
            return $this->redirectToRoute('polls.single', ['id' => $poll->get_id()]);
        }

        return $this->render('polls/comments/form.html.twig', [
            'iter' => $iter,
            'form' => $form,
        ]);
    }

    #[Route('/{id<\d+>}/update', name: 'poll_comments.update', methods: ['GET', 'POST'])]
    public function update(Request $request, int $poll_id, int $id): Response|RedirectResponse
    {
        $iter = $this->commentModel->get_iter($id);
        $poll = $this->pollModel->get_iter($poll_id);

        if ($iter['poll_id'] !== $poll_id)
            // Illegal situation! Redirect to a legal route.
            return $this->redirectToRoute('poll_comments.update', [
                'poll_id' => $iter['poll_id'],
                'id' => $id,
            ]);

        if (!$this->policy->userCanUpdate($iter))
            throw new UnauthorizedException('You are not allowed to edit this comment.');

        $form = $this->createForm(PollCommentType::class, $iter, ['mapped' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->commentModel->update($iter);
            return $this->redirectToRoute('polls.single', ['id' => $poll->get_id()]);
        }

        return $this->render('polls/comments/form.html.twig', [
            'iter' => $iter,
            'form' => $form,
        ]);
    }

    #[Route('/{id<\d+>}/delete', name: 'poll_comments.delete', methods: ['GET', 'POST'])]
    public function delete(Request $request, int $poll_id, int $id): Response|RedirectResponse
    {
        $iter = $this->commentModel->get_iter($id);
        $poll = $this->pollModel->get_iter($poll_id);

        if ($iter['poll_id'] !== $poll_id)
            // Illegal situation! Redirect to a legal route.
            return $this->redirectToRoute('poll_comments.update', [
                'poll_id' => $iter['poll_id'],
                'id' => $id,
            ]);

        if (!$this->policy->userCanDelete($iter))
            throw new UnauthorizedException('You are not allowed to delete this announcement.');

        $form = $this->createFormBuilder($iter)
            ->add('submit', SubmitType::class, ['label' => __('Delete'), 'color' => 'danger'])
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->commentModel->delete($iter);
            return $this->redirectToRoute('polls.single', ['id' => $poll->get_id()]);
        }

        return $this->render('polls/comments/confirm_delete.html.twig', [
            'iter' => $iter,
            'form' => $form,
        ]);
    }

    #[Route('/{id<\d+>}/likes', name: 'poll_comments.likes', methods: ['GET', 'POST'])]
    public function likes(
        Authentication $auth,
        DataModelPollCommentLike $likeModel,
        Request $request,
        int $poll_id,
        int $id,
    ): Response|RedirectResponse
    {
        $iter = $this->commentModel->get_iter($id);
        $poll = $this->pollModel->get_iter($poll_id);

        if ($iter['poll_id'] !== $poll_id)
            // Illegal situation! Redirect to a legal route.
            return $this->redirectToRoute('poll_comments.update', [
                'poll_id' => $iter['poll_id'],
                'id' => $id,
            ]);

        if (!$this->policy->userCanLike($iter)) {
            if ($auth->loggedIn)
                throw new UnauthorizedException('You are not allowed to like comments!');
            return $this->redirectToRoute('login', [
                'referrer' =>  $this->generateUrl('polls.single', ['id' => $iter->get_id()])
            ]);
        }

        $form = $this->createFormBuilder(null, ['csrf_token_id' => 'like_poll_comment_' . $iter->get_id()])
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

        if ($auth->loggedIn && isset($action)) {
            try {
                if ($action === 'like')
                    $likeModel->like($iter, $auth->identity->member());
                elseif ($action === 'unlike')
                    $likeModel->unlike($iter, $auth->identity->member());
            } catch (\Exception $e) {
                // Don't break duplicate requests
            }
        }

        if ($request->getContentTypeFormat() === 'json')
            return $this->json([
                'liked' => $auth->loggedIn && $iter->is_liked_by($auth->identity->member()),
                'likes' => count($iter->get_likes()),
            ]);

        return $this->redirectToRoute('polls.single', ['id' => $poll->get_id()]);
    }
}
