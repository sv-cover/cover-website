<?php

namespace App\Controller;

use App\Exception\UnauthorizedException;
use App\Form\DataTransformer\StringToDateTimeTransformer;
use App\Form\PollType;
use App\Service\Authentication;
use App\Service\Database;
use App\Service\Policy;
use App\Utils\UrlUtils;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;

class PollsController extends AbstractController
{
    CONST PAGE_SIZE = 10;

    private \DataModelPoll $model;

    public function __construct(
        private Database $db,
        private Policy $policy,
    ){
        $this->model = $db->getModel('DataModelPoll');
    }

    public function homepage(): Response
    {
        $poll = $this->model->get_current();

        if (!$this->policy->userCanRead($poll))
            $poll = null;

        return $this->render('polls/_homepage.html.twig', [
            'poll' => $poll,
        ]);
    }

    #[Route('/polls', name: 'polls.list', methods: ['GET'])]
    public function list(
        #[MapQueryParameter] int $page = 0,
    ): Response
    {
        $page_count = $this->model->count_polls() / self::PAGE_SIZE;

        if ($page > $page_count)
            throw $this->createNotFoundException();

        $iters = array_filter(
            $this->model->get_polls(self::PAGE_SIZE, $page * self::PAGE_SIZE),
            [$this->policy, 'userCanRead']
        );

        return $this->render('polls/list.html.twig', [
            'iters' => $iters,
            'page' => $page,
            'page_count' => $page_count,
        ]);
    }

    #[Route('/polls/create', name: 'polls.create', methods: ['GET', 'POST'])]
    public function create(Authentication $auth, Request $request): Response|RedirectResponse
    {
        $iter = $this->model->new_iter([
            'member_id' => $auth->identity->get('id'),
        ]);

        if (!$auth->loggedIn)
            throw new UnauthorizedException('You are not allowed to create polls.');

        if (!$this->policy->userCanCreate($iter))
            return $this->render('polls/no_create.html.twig');

        $form = $this->createForm(PollType::class, $iter, ['mapped' => false]);
        if (!$auth->identity->member_in_committee())
            $form->remove('committee_id');
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $id = $this->model->insert($iter);

            $options = $form['options']->getData();
            if (!empty($options))
                $this->model->set_options($iter, $options);

            return $this->redirectToRoute('polls.single', ['id' => $iter->get_id()]);
        }

        return $this->render('polls/form.html.twig', [
            'iter' => $iter,
            'form' => $form,
        ]);
    }

    #[Route('/polls/{id<\d+>}', name: 'polls.single', methods: ['GET'])]
    public function single(int $id): Response
    {
        $iter = $this->model->get_iter($id);

        if (!$this->policy->userCanRead($iter))
            throw new UnauthorizedException('You are not allowed to read this poll.');

        return $this->render('polls/single.html.twig', ['iter' => $iter]);
    }

    #[Route('/polls/{id<\d+>}/delete', name: 'polls.delete', methods: ['GET', 'POST'])]
    public function delete(int $id, Request $request): Response|RedirectResponse
    {
        $iter = $this->model->get_iter($id);

        if (!$this->policy->userCanDelete($iter))
            throw new UnauthorizedException('You are not allowed to delete this poll.');

        $form = $this->createFormBuilder($iter)
            ->add('submit', SubmitType::class, ['label' => __('Delete'), 'color' => 'danger'])
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->model->delete($iter);
            return $this->redirectToRoute('polls.list');
        }

        return $this->render('polls/confirm_delete.html.twig', [
            'iter' => $iter,
            'form' => $form,
        ]);
    }

    #[Route('/polls/{id<\d+>}/close', name: 'polls.close', methods: ['GET', 'POST'])]
    public function close(Request $request, UrlUtils $urlUtils, int $id): Response|RedirectResponse
    {
        $iter = $this->model->get_iter($id);

        if (!$this->policy->userCanClose($iter))
            throw new UnauthorizedException('You are not allowed to close this poll.');

        $form = $this->createFormBuilder($iter)
            ->add('submit', SubmitType::class, ['label' => __('Close poll'), 'color' => 'danger'])
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $iter['closed_on'] = new \DateTime();
            $this->model->update($iter);

            $next_url = $request->query->get('referrer', $this->generateUrl('polls.list'));
            return $this->redirect($urlUtils->validateRedirect($next_url));
        }

        return $this->render('polls/confirm_close.html.twig', [
            'iter' => $iter,
            'form' => $form,
        ]);
    }

    #[Route('/polls/{id<\d+>}/reopen', name: 'polls.reopen', methods: ['GET', 'POST'])]
    public function reopen(Request $request, UrlUtils $urlUtils, int $id): Response|RedirectResponse
    {
        $iter = $this->model->get_iter($id);

        if (!$this->policy->userCanClose($iter))
            throw new UnauthorizedException('You are not allowed to reopen this poll.');

        $iter['closed_on'] = null;

        $builder = $this->createFormBuilder($iter)
            ->add('closed_on', DateTimeType::class, [
                'label' => __('Closes on'),
                'constraints' => new Assert\Callback([
                    'callback' => [PollType::class, 'validate_closed_on'],
                ]),
                'widget' => 'single_text',
                'required' => false,
                'help' => __('People can vote until this date. If you provide no date, the poll closes as soon as the next poll is created.'),
            ])
            ->add('submit', SubmitType::class, ['label' => __('Reopen poll'), 'color' => 'danger']);
        $builder->get('closed_on')->addModelTransformer(new StringToDateTimeTransformer(null, null, 'Y-m-d H:i:s'));
        $form = $builder->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->model->update($iter);

            $next_url = $request->query->get('referrer', $this->generateUrl('polls.list'));
            return $this->redirect($urlUtils->validateRedirect($next_url));
        }

        return $this->render('polls/confirm_reopen.html.twig', [
            'iter' => $iter,
            'form' => $form,
        ]);
    }

    #[Route('/polls/{id<\d+>}/vote', name: 'polls.vote', methods: ['GET', 'POST'])]
    public function vote(Authentication $auth, Request $request, UrlUtils $urlUtils, int $id): Response|RedirectResponse
    {
        $iter = $this->model->get_iter($id);

        if (!$this->policy->userCanVote($iter)) {
            if ($auth->loggedIn)
                throw new UnauthorizedException('You are not allowed to vote!');
            return $this->redirectToRoute('login', [
                'referrer' =>  $this->generateUrl('polls.single', ['id' => $iter->get_id()])
            ]);
        }

        $form = $this->createFormBuilder(null, ['csrf_token_id' => 'vote_poll_' . $iter->get_id()])
            ->add('option', ChoiceType::class, [
                'expanded' => true,
                'choices' => $iter['options'],
                'choice_label' => function ($entity) {
                    return $entity['option'] ?? 'Unknown';
                },
                'choice_value' => function ($entity) {
                    return $entity['id'] ?? '';
                },
            ])
            ->add('submit', SubmitType::class)
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid())
            $this->model->set_member_vote(
                $form['option']->getData(),
                $auth->identity->member()
            );

        $next_url = $request->query->get('referrer', $this->generateUrl('polls.list'));
        return $this->redirect($urlUtils->validateRedirect($next_url));
    }

    #[Route('/polls/{id<\d+>}/likes', name: 'polls.likes', methods: ['GET', 'POST'])]
    public function likes(Authentication $auth, Request $request, UrlUtils $urlUtils, int $id): Response|RedirectResponse
    {
        $iter = $this->model->get_iter($id);

        if (!$this->policy->userCanLike($iter)) {
            if ($auth->loggedIn)
                throw new UnauthorizedException('You are not allowed to like polls!');
            return $this->redirectToRoute('login', [
                'referrer' =>  $this->generateUrl('polls.single', ['id' => $iter->get_id()])
            ]);
        }

        $form = $this->createFormBuilder(null, ['csrf_token_id' => 'like_poll_' . $iter->get_id()])
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

        $likeModel = $this->db->getModel('DataModelPollLike');

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

        $next_url = $request->query->get('referrer', $this->generateUrl('polls.single', [
            'id' => $iter->get_id(),
        ]));
        return $this->redirect($urlUtils->validateRedirect($next_url));
    }
}
