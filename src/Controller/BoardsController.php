<?php

namespace App\Controller;

use App\Exception\UnauthorizedException;
use App\Form\BoardType;
use App\Service\Database;
use App\Service\Policy;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class BoardsController extends AbstractController
{
    private \DataModelBesturen $model;

    public function __construct(
        private Database $db,
        private Policy $policy,
    ){
        $this->model = $db->getModel('DataModelBesturen');
    }

    #[Route('/boards', name: 'boards.list', methods: ['GET'])]
    public function list(): Response
    {
        $iters = $this->model->get();

        // Apply policy
        $iters = array_filter($iters, [$this->policy, 'userCanRead']);

        usort($iters, fn($a, $b) => -1 * strnatcmp($a->get('login'), $b->get('login')));

        return $this->render('boards/list.html.twig', ['iters' => $iters]);
    }

    #[Route('/boards/create', name: 'boards.create', methods: ['GET', 'POST'])]
    public function create(Request $request): Response|RedirectResponse
    {
        $iter = $this->model->new_iter();

        if (!$this->policy->userCanCreate($iter))
            throw new UnauthorizedException('You are not allowed to create boards.');

        $form = $this->createForm(BoardType::class, $iter, ['mapped' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $pageModel = $db->getModel('DataModelEditable');

            $page = $pageModel->new_iter([
                'committee_id' => \COMMISSIE_BESTUUR,
                'titel' => $iter['naam']
            ]);

            $iter['page_id'] = $pageModel->insert($page, true);

            $id = $this->model->insert($iter);
            return $this->redirectToRoute('boards.single', ['id' => $iter->get_id()]);
        }

        return $this->render('boards/form.html.twig', [
            'iter' => $iter,
            'form' => $form,
        ]);
    }

    #[Route('/boards/{id<\d+>}/update', name: 'boards.update', methods: ['GET', 'POST'])]
    public function update(int $id, Request $request): Response|RedirectResponse
    {
        $iter = $this->model->get_iter($id);

        if (!$this->policy->userCanUpdate($iter))
            throw new UnauthorizedException('You are not allowed to edit this board.');

        $form = $this->createForm(BoardType::class, $iter, ['mapped' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $page = $iter['page'];
            $page->set('titel', $iter['naam']);
            $db->getModel('DataModelEditable')->update($page);

            $this->model->update($iter);
            return $this->redirectToRoute('boards.single', ['id' => $iter->get_id()]);
        }

        return $this->render('boards/form.html.twig', [
            'iter' => $iter,
            'form' => $form,
        ]);
    }
}
