<?php

namespace App\Controller;

use App\DataModel\DataModelCommissie;
use App\Exception\UnauthorizedException;
use App\Form\CommitteeType;
use App\Form\DataTransformer\IntToBooleanTransformer;
use App\Legacy\Authentication\Authentication;
use App\Legacy\Policy\Policy;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Attribute\Route;

class CommitteesController extends AbstractController
{
    public function __construct(
        private DataModelCommissie $model,
        private Policy $policy,
    ) {
    }

    #[Route('/committees', name: 'committees.list', methods: ['GET'])]
    public function list(): Response
    {
        $committees = $this->model->get(DataModelCommissie::TYPE_COMMITTEE);
        $working_groups = $this->model->get(DataModelCommissie::TYPE_WORKING_GROUP);

        return $this->render('committees/list.html.twig', [
            'committees' => array_filter($committees, [$this->policy, 'userCanRead']),
            'working_groups' => array_filter($working_groups, [$this->policy, 'userCanRead']),
        ]);
    }

    /**
     * The Thrash! All (including deleted) committees/groups/others/etc
     */
    #[Route('/committees/archive', name: 'committees.archive', methods: ['GET'])]
    public function archive(): Response
    {
        // If you can't create a committee, you won't need the archive either.
        if (!$this->policy->userCanCreate('DataModelCommissie'))
            throw new UnauthorizedException('You are not allowed to view the committee archive.');

        $iters = $this->model->get(null, true);

        return $this->render('committees/archive.html.twig', ['iters' => $iters]);
    }

    #[Route('/committees/slide/{slug}', name: 'committees.slide', methods: ['GET'])]
    public function slide(?string $slug = null): Response
    {
        if (isset($slug))
            $committee = $this->model->find_one(['login' => $slug]);
        else
            // Pick a random committee
            $committee = $this->model->get_random(DataModelCommissie::TYPE_COMMITTEE, true);

        return $this->render('committees/slide.html.twig', ['committee' => $committee]);
    }

    #[Route('/committees/create', name: 'committees.create', methods: ['GET', 'POST'])]
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

            return $this->redirectToRoute('committees.single', ['slug' => $iter['login']]);
        }

        return $this->render('committees/form.html.twig', [
            'iter' => $iter,
            'form' => $form,
            'functions' => $this->model->get_functies(),
        ]);
    }

    #[Route('/committees/{slug}', name: 'committees.single', methods: ['GET'], priority: -1)]
    public function single(string $slug): Response
    {
        $iter = $this->model->find_one(['login' => $slug]);

        if ($iter['hidden'])
            throw $this->createNotFoundException('This committee/group is no longer active.');

        if (!$this->policy->userCanRead($iter))
            throw new UnauthorizedException('You are not allowed to see this committee.');

        return $this->render('committees/single.html.twig', ['iter' => $iter]);
    }

    #[Route('/committees/{slug}/update', name: 'committees.update', methods: ['GET', 'POST'])]
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

            return $this->redirectToRoute('committees.single', ['slug' => $iter['login']]);
        }

        return $this->render('committees/form.html.twig', [
            'iter' => $iter,
            'form' => $form,
            'functions' => $this->model->get_functies(),
        ]);
    }

    #[Route('/committees/{slug}/delete', name: 'committees.delete', methods: ['GET', 'POST'])]
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

            return $this->redirectToRoute('committees.list');
        }

        return $this->render('committees/confirm_delete.html.twig', [
            'iter' => $iter,
            'form' => $form,
        ]);
    }

    #[Route('/committees/{slug}/interest', name: 'committees.interest', methods: ['POST'])]
    public function interest(Authentication $auth, MailerInterface $mailer, Request $request, string $slug): RedirectResponse
    {
        if (!$auth->getIdentity()->is_member())
            throw new UnauthorizedException('Only members can apply for a committee.');

        $iter = $this->model->find_one(['login' => $slug]);

        if (!$this->policy->userCanRead($iter))
            throw new UnauthorizedException('You are not allowed to see this committee.');

        $form = $this->createFormBuilder($iter, ['csrf_token_id' => 'committee_interest_' . $iter['id']])
            ->add('submit', SubmitType::class, ['label' => __('Delete'), 'color' => 'danger'])
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $member = $auth->getIdentity()->member();

            if ($this->getParameter('app.committee_interest_log'))
                error_log(sprintf(
                    "%s - %s (%d) is interested in %s.\n",
                    date('c'),
                    $member['full_name'],
                    $member['id'],
                    $iter['naam']
                ), 3, $this->getParameter('app.committee_interest_log'));

            $email = (new TemplatedEmail())
                ->to('intern@svcover.nl')
                ->cc($member['email'])
                ->replyTo($member['email'])
                ->subject("{$member['voornaam']} is interested in {$iter['naam']}")
                ->htmlTemplate('emails/committee_interest.html.twig')
                ->context([
                    'committee' => $iter,
                    'member' => $member,
                ])
            ;
            $mailer->send($email);

            $this->addFlash('committee_interest', __('Cool! We’ve notified the Commissioner of Internal Affairs for you!'));
        }

        return $this->redirectToRoute('committees.single', ['slug' => $iter['login']]);
    }
}
