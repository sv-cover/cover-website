<?php

namespace App\Controller;

use App\DataModel\DataModelPartner;
use App\Exception\UnauthorizedException;
use App\Form\PartnerType;
use App\Legacy\Policy\Policy;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

class PartnersController extends AbstractController
{
    public function __construct(
        private DataModelPartner $model,
        private Policy $policy,
    ){
    }

    #[Route('/partners', name: 'partners.list', methods: ['GET'])]
    public function list(): Response|RedirectResponse
    {
        if (!$this->policy->userCanUpdate($this->model->new_iter()))
            return $this->redirectToRoute('career');

        $iters = $this->model->get();

        // Apply policy
        $iters = array_filter($iters, [$this->policy, 'userCanRead']);

        \usort($iters, function($a, $b) {
            return \strcasecmp($a['name'], $b['name']);
        });

        return $this->render('partners/list.html.twig', ['iters' => $iters]);
    }

    #[Route('/partners/autocomplete', name: 'partners.autocomplete', methods: ['GET'])]
    public function autocomplete(#[MapQueryParameter] string $search): Response
    {
        $partners = $this->model->find(['name__contains' => $search]);

        $data = [];

        foreach ($partners as $partner)
            $data[] = [
                'id' => $partner['id'],
                'name' => $partner['name'],
            ];

        return $this->json($data);
    }

    #[Route('/partners/create', name: 'partners.create', methods: ['GET', 'POST'])]
    public function create(Request $request): Response|RedirectResponse
    {
        $iter = $this->model->new_iter();

        if (!$this->policy->userCanCreate($iter))
            throw new UnauthorizedException('You are not allowed to create partners.');

        $form = $this->createForm(PartnerType::class, $iter, ['mapped' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $id = $this->model->insert($iter);
            return $this->redirectToRoute('partners.single', ['id' => $iter->get_id()]);
        }

        return $this->render('partners/form.html.twig', [
            'iter' => $iter,
            'form' => $form,
        ]);
    }

    #[Route('/partners/{id<\d+>}', name: 'partners.single', methods: ['GET'])]
    public function single(int $id): Response
    {
        $iter = $this->model->get_iter($id);

        if (!$this->policy->userCanRead($iter))
            throw new UnauthorizedException('You are not allowed to see this partner.');

        return $this->render('partners/single.html.twig', ['iter' => $iter]);
    }

    #[Route('/partners/{id<\d+>}/update', name: 'partners.update', methods: ['GET', 'POST'])]
    public function update(int $id, Request $request): Response|RedirectResponse
    {
        $iter = $this->model->get_iter($id);

        if (!$this->policy->userCanUpdate($iter))
            throw new UnauthorizedException('You are not allowed to edit this partner.');

        $form = $this->createForm(PartnerType::class, $iter, ['mapped' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->model->update($iter);
            return $this->redirectToRoute('partners.single', ['id' => $iter->get_id()]);
        }

        return $this->render('partners/form.html.twig', [
            'iter' => $iter,
            'form' => $form,
        ]);
    }

    #[Route('/partners/{id<\d+>}/delete', name: 'partners.delete', methods: ['GET', 'POST'])]
    public function delete(int $id, Request $request): Response|RedirectResponse
    {
        $iter = $this->model->get_iter($id);

        if (!$this->policy->userCanDelete($iter))
            throw new UnauthorizedException('You are not allowed to delete this partner.');

        $form = $this->createFormBuilder($iter)
            ->add('submit', SubmitType::class, ['label' => __('Delete'), 'color' => 'danger'])
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->model->delete($iter);
            return $this->redirectToRoute('partners.list');
        }

        return $this->render('partners/confirm_delete.html.twig', [
            'iter' => $iter,
            'form' => $form,
        ]);
    }

    /**
     * Render partners fragment in the footer on most pages.
     */
    public function footer(): Response
    {
        $iters = $this->model->find(['has_banner_visible' => 1]);

        // Apply policy
        $iters = array_filter($iters, [$this->policy, 'userCanRead']);

        // Shuffle the banners
        shuffle($iters);

        // Ensure sort_order (main > sponsor > other) is maintained, but partners are otherwise shuffled.
        usort($iters, function($a, $b) {
            return $a['sort_order'] <=> $b['sort_order'];
        });

        return $this->render('partners/_footer.html.twig', [
            'main_partners' => array_filter($iters, fn($p) => $p['type'] == DataModelPartner::TYPE_MAIN_SPONSOR),
            'partners' => array_filter($iters, fn($p) => $p['type'] != DataModelPartner::TYPE_MAIN_SPONSOR),
        ]);
    }
}
