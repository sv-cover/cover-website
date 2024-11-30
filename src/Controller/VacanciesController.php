<?php

namespace App\Controller;

use App\Exception\UnauthorizedException;
use App\Form\VacancyType;
use App\Service\Database;
use App\Service\Policy;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class VacanciesController extends AbstractController
{
    private \DataModelVacancy $model;

    public function __construct(
        private Database $db,
        private Policy $policy,
    ){
        $this->model = $db->getModel('DataModelVacancy');
    }

    #[Route('/vacancies', name: 'vacancies.list', methods: ['GET'])]
    public function list(Request $request): Response
    {
        $filter = array_intersect_key(
            $request->query->all(),
            array_flip($this->model::FILTER_FIELDS),
        );

        $iters = $this->model->filter($filter);

        // Apply policy
        $iters = array_filter($iters, [$this->policy, 'userCanRead']);

        return $this->render('vacancies/list.html.twig', [
            'iters' => $iters,
            'filters' => $filter,
            'partners' => $this->model->partners(),
        ]);
    }

    #[Route('/vacancies/create', name: 'vacancies.create', methods: ['GET', 'POST'])]
    public function create(Request $request): Response|RedirectResponse
    {
        $iter = $this->model->new_iter();

        if (!$this->policy->userCanCreate($iter))
            throw new UnauthorizedException('You are not allowed to create vacancies.');

        $form = $this->createForm(VacancyType::class, $iter, ['mapped' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $id = $this->model->insert($iter);
            return $this->redirectToRoute('vacancies.single', ['id' => $iter->get_id()]);
        }

        return $this->render('vacancies/form.html.twig', [
            'iter' => $iter,
            'form' => $form,
        ]);
    }

    #[Route('/vacancies/{id<\d+>}', name: 'vacancies.single', methods: ['GET'])]
    public function single(int $id): Response
    {
        $iter = $this->model->get_iter($id);

        if (!$this->policy->userCanRead($iter))
            throw new UnauthorizedException('You are not allowed to read this vacancy.');

        return $this->render('vacancies/single.html.twig', ['iter' => $iter]);
    }

    #[Route('/vacancies/{id<\d+>}/update', name: 'vacancies.update', methods: ['GET', 'POST'])]
    public function update(int $id, Request $request): Response|RedirectResponse
    {
        $iter = $this->model->get_iter($id);

        if (!$this->policy->userCanUpdate($iter))
            throw new UnauthorizedException('You are not allowed to edit this vacancy.');

        $form = $this->createForm(VacancyType::class, $iter, ['mapped' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->model->update($iter);
            return $this->redirectToRoute('vacancies.single', ['id' => $iter->get_id()]);
        }

        return $this->render('vacancies/form.html.twig', [
            'iter' => $iter,
            'form' => $form,
        ]);
    }

    #[Route('/vacancies/{id<\d+>}/delete', name: 'vacancies.delete', methods: ['GET', 'POST'])]
    public function delete(int $id, Request $request): Response|RedirectResponse
    {
        $iter = $this->model->get_iter($id);

        if (!$this->policy->userCanDelete($iter))
            throw new UnauthorizedException('You are not allowed to delete this vacancy.');

        $form = $this->createFormBuilder($iter)
            ->add('submit', SubmitType::class, ['label' => __('Delete'), 'color' => 'danger'])
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->model->delete($iter);
            return $this->redirectToRoute('vacancies.list');
        }

        return $this->render('vacancies/confirm_delete.html.twig', [
            'iter' => $iter,
            'form' => $form,
        ]);
    }
}
