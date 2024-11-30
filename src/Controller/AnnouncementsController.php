<?php

namespace App\Controller;

use App\Exception\UnauthorizedException;
use App\Form\AnnouncementType;
use App\Service\Database;
use App\Service\Policy;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class AnnouncementsController extends AbstractController
{
    private \DataModelAnnouncement $model;

    public function __construct(
        private Database $db,
        private Policy $policy,
    ){
        $this->model = $db->getModel('DataModelAnnouncement');
    }

    public function homepage(): Response
    {
        $iters = $this->model->get_latest();

        // Apply policy
        $iters = array_filter($iters, [$this->policy, 'userCanRead']);

        return $this->render('announcements/_announcements.html.twig', [
            'iters' => $iters,
            'show_all_button' => true,
        ]);
    }

    #[Route('/announcements', name: 'announcements.list', methods: ['GET'])]
    public function list(): Response
    {
        $iters = $this->model->get();

        // Apply policy
        $iters = array_filter($iters, [$this->policy, 'userCanRead']);

        return $this->render('announcements/list.html.twig', [
            'iters' => $iters,
        ]);
    }

    #[Route('/announcements/create', name: 'announcements.create', methods: ['GET', 'POST'])]
    public function create(Request $request): Response|RedirectResponse
    {
        $iter = $this->model->new_iter();

        if (!$this->policy->userCanCreate($iter))
            throw new UnauthorizedException('You are not allowed to create announcements.');

        $form = $this->createForm(AnnouncementType::class, $iter, ['mapped' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $id = $this->model->insert($iter);
            return $this->redirectToRoute('announcements.single', ['id' => $iter->get_id()]);
        }

        return $this->render('announcements/form.html.twig', [
            'iter' => $iter,
            'form' => $form,
        ]);
    }

    #[Route('/announcements/{id<\d+>}', name: 'announcements.single', methods: ['GET'])]
    public function single(int $id): Response
    {
        $iter = $this->model->get_iter($id);

        if (!$this->policy->userCanRead($iter))
            throw new UnauthorizedException('You are not allowed to read this announcement.');

        return $this->render('announcements/single.html.twig', ['iter' => $iter]);
    }

    #[Route('/announcements/{id<\d+>}/update', name: 'announcements.update', methods: ['GET', 'POST'])]
    public function update(Request $request, int $id): Response|RedirectResponse
    {
        $iter = $this->model->get_iter($id);

        if (!$this->policy->userCanUpdate($iter))
            throw new UnauthorizedException('You are not allowed to edit this announcement.');

        $form = $this->createForm(AnnouncementType::class, $iter, ['mapped' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->model->update($iter);
            return $this->redirectToRoute('announcements.single', ['id' => $iter->get_id()]);
        }

        return $this->render('announcements/form.html.twig', [
            'iter' => $iter,
            'form' => $form,
        ]);
    }

    #[Route('/announcements/{id<\d+>}/delete', name: 'announcements.delete', methods: ['GET', 'POST'])]
    public function delete(Request $request, int $id): Response|RedirectResponse
    {
        $iter = $this->model->get_iter($id);

        if (!$this->policy->userCanDelete($iter))
            throw new UnauthorizedException('You are not allowed to delete this announcement.');

        $form = $this->createFormBuilder($iter)
            ->add('submit', SubmitType::class, ['label' => __('Delete'), 'color' => 'danger'])
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->model->delete($iter);
            return $this->redirectToRoute('announcements.list');
        }

        return $this->render('announcements/confirm_delete.html.twig', [
            'iter' => $iter,
            'form' => $form,
        ]);
    }
}
