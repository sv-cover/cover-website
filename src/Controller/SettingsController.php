<?php

namespace App\Controller;

use App\DataModel\DataModelConfiguratie;
use App\Exception\UnauthorizedException;
use App\Form\SettingsType;
use App\Legacy\Policy\Policy;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class SettingsController extends AbstractController
{
    public function __construct(
        private DataModelConfiguratie $model,
        private Policy $policy,
    ) {
    }

    #[Route('/settings', name: 'settings.list', methods: ['GET'])]
    public function list(): Response
    {
        $iters = $this->model->get();

        // Apply policy
        $iters = array_filter($iters, [$this->policy, 'userCanRead']);

        usort($iters, fn($a, $b): int => strcasecmp($a['key'], $b['key']));

        return $this->render('settings/list.html.twig', [
            'iters' => $iters,
        ]);
    }

    #[Route('/settings/create', name: 'settings.create', methods: ['GET', 'POST'])]
    public function create(Request $request): Response|RedirectResponse
    {
        $iter = $this->model->new_iter();

        if (!$this->policy->userCanCreate($iter))
            throw new UnauthorizedException('You are not allowed to create settings.');

        $form = $this->createForm(SettingsType::class, $iter, ['mapped' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $id = $this->model->insert($iter);
            return $this->redirectToRoute('settings.single', ['id' => $iter->get_id()]);
        }

        return $this->render('settings/form.html.twig', [
            'iter' => $iter,
            'form' => $form,
        ]);
    }

    #[Route('/settings/{id}/update', name: 'settings.update', methods: ['GET', 'POST'])]
    public function update(string $id, Request $request): Response|RedirectResponse
    {
        $iter = $this->model->get_iter($id);

        if (!$this->policy->userCanUpdate($iter))
            throw new UnauthorizedException('You are not allowed to edit this setting.');

        $form = $this->createForm(SettingsType::class, $iter, ['mapped' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->model->update($iter);
            return $this->redirectToRoute('settings.list');
        }

        return $this->render('settings/form.html.twig', [
            'iter' => $iter,
            'form' => $form,
        ]);
    }

    #[Route('/settings/{id}/delete', name: 'settings.delete', methods: ['GET', 'POST'])]
    public function delete(string $id, Request $request): Response|RedirectResponse
    {
        $iter = $this->model->get_iter($id);

        if (!$this->policy->userCanDelete($iter))
            throw new UnauthorizedException('You are not allowed to delete this setting.');

        $form = $this->createFormBuilder($iter)
            ->add('submit', SubmitType::class, ['label' => __('Delete'), 'color' => 'danger'])
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->model->delete($iter);
            return $this->redirectToRoute('settings.list');
        }

        return $this->render('settings/confirm_delete.html.twig', [
            'iter' => $iter,
            'form' => $form,
        ]);
    }
}