<?php

namespace App\Controller;

use App\DataModel\DataModelCommissie;
use App\Exception\UnauthorizedException;
use App\Form\CommitteeType;
use App\Form\DataTransformer\IntToBooleanTransformer;
use App\Legacy\Policy\Policy;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class GroupsController extends AbstractController
{
    public function __construct(
        private DataModelCommissie $model,
        private Policy $policy,
    ) {
    }

    /**
     * The Thrash! All (including deleted) committees/groups/others/societies/etc
     */
    #[Route('/groups/archive', name: 'groups.archive', methods: ['GET'])]
    public function archive(): Response
    {
        // If you can't create a group, you won't need the archive either.
        if (!$this->policy->userCanCreate('DataModelCommissie'))
            throw new UnauthorizedException('You are not allowed to view the groups archive.');

        $iters = $this->model->get(null, true);

        return $this->render('groups/archive.html.twig', ['iters' => $iters]);
    }

    #[Route('/groups/create', name: 'groups.create', methods: ['GET', 'POST'])]
    public function create(Request $request): Response|RedirectResponse
    {
        $iter = $this->model->new_iter([
            'type' => DataModelCommissie::TYPE_COMMITTEE
        ]);

        if (!$this->policy->userCanCreate($iter))
            throw new UnauthorizedException('You are not allowed to create groups.');

        $form = $this->createForm(CommitteeType::class, $iter, ['mapped' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $id = $this->model->insert($iter);

            $members = $form['members']->getData();
            if (!empty($members))
                $this->model->set_members($iter, $members);

            return $this->redirect($this->model->get_url_for_iter($iter));
        }

        return $this->render('groups/form.html.twig', [
            'iter' => $iter,
            'form' => $form,
            'functions' => $this->model->get_functies(),
        ]);
    }

    #[Route('/groups/{type}/{slug}', name: 'groups.single', methods: ['GET'], priority: -1)]
    public function single(string $type, string $slug): Response
    {
		$typeId = array_search($type, DataModelCommissie::TYPE_OPTIONS, strict: true);

        $iter = $this->model->find_one(['login' => $slug, 'type' => $typeId]);

        if (!isset($iter))
            throw $this->createNotFoundException('Group not found.');

        if ($iter['hidden'])
            throw $this->createNotFoundException('This group is no longer active.');

        if (!$this->policy->userCanRead($iter))
            throw new UnauthorizedException('You are not allowed to see this group.');

        return $this->render('groups/single.html.twig', ['iter' => $iter]);
    }
    #[Route('/groups/{slug}/update', name: 'groups.update', methods: ['GET', 'POST'])]
    public function update(string $slug, Request $request, FormFactoryInterface $formFactory): Response|RedirectResponse
    {
        $iter = $this->model->find_one(['login' => $slug]);

        if (!$this->policy->userCanUpdate($iter))
            throw new UnauthorizedException('You are not allowed to edit this group.');

        $builder = $formFactory->createBuilder(CommitteeType::class, $iter, ['mapped' => false]);

        // Add field to reactivate deactivated groups
        if (!empty($iter['hidden'])) {
            $builder->add('hidden', CheckboxType::class, [
                'label' => __('This group is deactivated.'),
                'help' => __('Uncheck this box and submit to reactivate.'),
                'required' => false,
            ]);
            $builder->get('hidden')->addModelTransformer(new IntToBooleanTransformer());
        }

        // Populate members field
        // TODO: this is terribly inefficient
        $members = array_map(
            fn($member) => ['member_id' => $member['id'], 'functie' => $member['functie']],
            $iter->get_members()
        );
        $builder->get('members')->setData($members);

        $form = $builder->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->model->update($iter);

            $members = $form['members']->getData();
            $this->model->set_members($iter, empty($members) ? [] : $members);

			if ($iter['hidden']) {
				return $this->redirectToRoute('groups.archive');
			}

			return $this->redirect($iter->get_url());
        }

        return $this->render('groups/form.html.twig', [
            'iter' => $iter,
            'form' => $form,
            'functions' => $this->model->get_functies(),
        ]);
    }

    #[Route('/groups/{slug}/delete', name: 'groups.delete', methods: ['GET', 'POST'])]
    public function delete(string $slug, Request $request): Response|RedirectResponse
    {
        $iter = $this->model->find_one(['login' => $slug]);

        if (!$this->policy->userCanDelete($iter))
            throw new UnauthorizedException('You are not allowed to delete this group.');

        $form = $this->createFormBuilder($iter)
            ->add('submit', SubmitType::class, ['label' => __('Delete'), 'color' => 'danger'])
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Some committees already have pages etc. We will mark the committee as hidden.
            // That way they remain in the history of Cover and could, if needed, be reactivated.
            $iter['hidden'] = true;

            // We'll also remove all its members at least
            $iter['members'] = [];

            $this->model->update($iter);

			if ($iter['type'] === DataModelCommissie::TYPE_SOCIETY) {
				return $this->redirectToRoute('societies.list');
			}

            return $this->redirectToRoute('committees.list');
        }

        return $this->render('groups/confirm_delete.html.twig', [
            'iter' => $iter,
            'form' => $form,
        ]);
    }
}
