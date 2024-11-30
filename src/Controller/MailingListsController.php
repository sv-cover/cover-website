<?php

namespace App\Controller;

use App\Exception\NotFoundException;
use App\Exception\UnauthorizedException;
use App\Form\MailingListType;
use App\Form\Type\MemberIdType;
use App\Legacy\Email\MessagePart;
use App\Service\Authentication;
use App\Service\Database;
use App\Service\Policy;
use App\Utils\UrlUtils;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;

class MailingListsController extends AbstractController
{
    private \DataModelMailinglist $model;

    public function __construct(
        private Database $db,
        private Policy $policy,
    ){
        $this->model = $db->getModel('DataModelMailinglist');
    }

    #[Route('/mailing_lists', name: 'mailing_lists.list', methods: ['GET'])]
    public function list(): Response
    {
        $iters = $this->model->get();

        // Apply policy
        $iters = array_filter($iters, [$this->policy, 'userCanRead']);

        usort($iters, fn($a, $b): int => strcasecmp($a['naam'], $b['naam']));

        return $this->render('mailing_lists/list.html.twig', [
            'iters' => $iters,
        ]);
    }

    #[Route('/mailing_lists/create', name: 'mailing_lists.create', methods: ['GET', 'POST'])]
    public function create(Request $request): Response|RedirectResponse
    {
        $iter = $this->model->new_iter(['has_members' => true, 'tag' => 'Cover']);

        if (!$this->policy->userCanCreate($iter))
            throw new UnauthorizedException('You are not allowed to create mailing_lists.');

        $form = $this->createForm(MailingListType::class, $iter, ['mapped' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $id = $this->model->insert($iter);
            return $this->redirectToRoute('mailing_lists.single', ['id' => $iter->get_id()]);
        }

        return $this->render('mailing_lists/form.html.twig', [
            'iter' => $iter,
            'form' => $form,
        ]);
    }

    #[Route('/mailing_lists/{id<\d+>}', name: 'mailing_lists.single', methods: ['GET'])]
    public function single(int $id): Response
    {
        $iter = $this->model->get_iter($id);

        if (!$this->policy->userCanRead($iter))
            throw new UnauthorizedException('You are not allowed to see this mailing list.');

        return $this->render('mailing_lists/single.html.twig', ['iter' => $iter]);
    }

    #[Route('/mailing_lists/{id<\d+>}/update', name: 'mailing_lists.update', methods: ['GET', 'POST'])]
    public function update(int $id, Request $request): Response|RedirectResponse
    {
        $iter = $this->model->get_iter($id);

        if (!$this->policy->userCanUpdate($iter))
            throw new UnauthorizedException('You are not allowed to edit this mailing list.');

        $form = $this->createForm(MailingListType::class, $iter, ['mapped' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->model->update($iter);
            return $this->redirectToRoute('mailing_lists.single', ['id' => $iter->get_id()]);
        }

        return $this->render('mailing_lists/form.html.twig', [
            'iter' => $iter,
            'form' => $form,
        ]);
    }

    /**
     * Endpoint to allow list owners to manually subscribe members.
     *
     * TODO: better naming to differentiate between admin and user actions
     * TODO: instead of checking whether current user can update the list,
     * check whether they can create new subscription iterators according
     * to the policy?
     */
    #[Route('/mailing_lists/{id<\d+>}/subscribe_member', name: 'mailing_lists.subscribe_member', methods: ['GET', 'POST'])]
    public function subscribeMember(int $id, Request $request): Response|RedirectResponse
    {
        $list = $this->model->get_iter($id);

        if (!$this->policy->userCanUpdate($list))
            throw new UnauthorizedException('You cannot subscribe members to this mailing list');

        $form = $this->createFormBuilder()
            ->add('member_id', MemberIdType::class, [
                'label' => __('Member'),
            ])
            ->add('submit', SubmitType::class)
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $member = $this->db->getModel('DataModelMember')->get_iter($form->get('member_id')->getData());
            $this->db->getModel('DataModelMailinglistSubscription')->subscribe_member($list, $member);
            return $this->redirectToRoute('mailing_lists.single', ['id' => $list->get_id()]);
        }

        return $this->render('mailing_lists/subscribe_member_form.html.twig', [
            'list' => $list,
            'form' => $form,
        ]);
    }

    /**
     * Endpoint to allow list owners to manually subscribe non-members.
     *
     * TODO: better naming to differentiate between admin and user actions
     */
    #[Route('/mailing_lists/{id<\d+>}/subscribe_guest', name: 'mailing_lists.subscribe_guest', methods: ['GET', 'POST'])]
    public function subscribeGuest(int $id, Request $request): Response|RedirectResponse
    {
        $list = $this->model->get_iter($id);

        if (!$this->policy->userCanUpdate($list))
            throw new UnauthorizedException('You cannot subscribe non-members to this mailing list');

        $form = $this->createFormBuilder()
            ->add('name', TextType::class, [
                'label' => __('Name'),
                'constraints' => [new Assert\NotBlank()],
            ])
            ->add('email', EmailType::class, [
                'label' => __('E-mail address'),
                'constraints' => [new Assert\NotBlank(), new Assert\Email()],
            ])
            ->add('submit', SubmitType::class)
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->db->getModel('DataModelMailinglistSubscription')->subscribe_guest(
                $list,
                $form->get('name')->getData(),
                $form->get('email')->getData(),
            );
            return $this->redirectToRoute('mailing_lists.single', ['id' => $list->get_id()]);
        }

        return $this->render('mailing_lists/subscribe_guest_form.html.twig', [
            'list' => $list,
            'form' => $form,
        ]);
    }

    /**
     * Endpoint to allow list owners to manually unsubscribe members and non-members.
     */
    #[Route('/mailing_lists/{id<\d+>}/unsubscribe', name: 'mailing_lists.unsubscribe', methods: ['POST'])]
    public function unsubscribe(int $id, Request $request): RedirectResponse
    {
        $list = $this->model->get_iter($id);

        if (!$this->policy->userCanUpdate($list))
            throw new UnauthorizedException('You cannot unsubscribe people from this mailing list');

        $form = $this->createFormBuilder(null, ['csrf_token_id' => 'unsubscribe_' . $list['id']])
            ->add('unsubscribe', ChoiceType::class, [
                'expanded' => true,
                'multiple' => true,
                'choices' => $list->get_subscriptions(),
                'choice_label' => function ($entity) {
                    return $entity['name'] ?? $entity['lid_id'] ?? 'Unknown';
                },
                'choice_value' => function ($entity) {
                    return $entity['id'] ?? '';
                },
            ])
            ->add('submit', SubmitType::class)
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid())
            foreach ($form->get('unsubscribe')->getData() as $subscription)
                $subscription->cancel();

        return $this->redirectToRoute('mailing_lists.single', ['id' => $list->get_id()]);
    }

    /**
     * Endpoint to allow members to (un)subscribe from their profile page.
     */
    #[Route('/mailing_lists/{id<\d+>}/subscribe', name: 'mailing_lists.subsciption.create', methods: ['GET', 'POST'])]
    public function subscriptionCreate(int $id, Request $request, Authentication $auth, UrlUtils $urlUtils): RedirectResponse
    {
        $list = $this->model->get_iter($id);

        if (!$auth->loggedIn)
            throw new UnauthorizedException('You need to log in to manage your mailinglist subscriptions');

        $member = $auth->identity->member();

        $form = $this->createFormBuilder(null, ['csrf_token_id' => 'mailinglist_subscription_' . $list['id']])
            ->add('subscribe', CheckboxType::class, [
                'label' => __('Subscribe'),
                'required' => false,
            ])
            ->add('do_subscribe', SubmitType::class, ['label' => __('Subscribe')])
            ->add('do_unsubscribe', SubmitType::class, ['label' => __('Unsubscribe')])
            ->getForm();
        $form->handleRequest($request);

        $model = $this->db->getModel('DataModelMailinglistSubscription');

        if ($form->isSubmitted() && $form->isValid()) {
            // `subscribe` changes only if JS works, otherwise `do_subscribe` and `do_unsubscribe` should override whatever value is set.
            // So only subscribe if either `do_subscribe` is clicked or `subscribe` is checked without `do_unsubscribe` being clicked.
            $subscribe = (
                $form->get('do_subscribe')->isClicked()
                || (
                    $form->get('subscribe')->getData()
                    && !$form->get('do_unsubscribe')->isClicked()
                )
            );

            if ($subscribe && $this->policy->userCanSubscribe($list))
                $model->subscribe_member($list, $member);
            elseif (!$subscribe && $this->policy->userCanUnsubscribe($list))
                $model->unsubscribe_member($list, $member);
        }

        $referrer = $request->query->get(
            'referrer',
            $this->generateUrl('profile.mailing_lists', ['member_id' => $member->get_id()])
        );

        return $this->redirect($urlUtils->validateRedirect($referrer));
    }

    /**
     * Endpoint to allow people to unsubscribe through an unsubscribe link.
     */
    #[Route('/mailing_lists/subscription/{id}/unsubscribe', name: 'mailing_lists.subscription.unsubscribe', methods: ['GET', 'POST'])]
    public function subscriptionDelete(string $id, Request $request): Response|RedirectResponse
    {
        $model = $this->db->getModel('DataModelMailinglistSubscription');

        try {
            $subscription = $model->get_iter($id);
        } catch (NotFoundException $e) {
            if (preg_match('/^(\d+)\-(\d+)$/', $id, $match))
                $subscription = $model->new_iter([
                    'opgezegd_op' => '1993-09-20 00:00:00',
                    'lid_id' => (int) $match[2],
                    'mailinglijst_id' => (int) $match[1],
                ]);
            else
                throw $e;
        }

        $list = $subscription['mailinglist'];

        $form = $this->createFormBuilder()
            ->add('submit', SubmitType::class, ['label' => 'Unsubscribe'])
            ->getForm();
        $form->handleRequest($request);

        if ($subscription->is_active() && $form->isSubmitted() && $form->isValid())
            $subscription->cancel();

        return $this->render('mailing_lists/unsubscribe_form.html.twig', [
            'list' => $list,
            'subscription' => $subscription,
            'form' => $form,
        ]);
    }

    #[Route('/mailing_lists/{id<\d+>}/autoresponder/{autoresponder}/update', name: 'mailing_lists.autoresponder.update', methods: ['GET', 'POST'])]
    public function autoresponderUpdate(int $id, string $autoresponder, Request $request): Response|RedirectResponse
    {
        $iter = $this->model->get_iter($id);

        if (!$this->policy->userCanUpdate($iter))
            throw new UnauthorizedException('You are not allowed to edit this mailing list.');

        if (!in_array($autoresponder, ['on_subscription', 'on_first_email']))
            throw new \InvalidArgumentException('Invalid value for autoresponder parameter');


        $builder = $this->createFormBuilder($iter)
            ->add($autoresponder . '_subject', TextType::class, [
                'label' => __('Subject'),
                'required' => false,
            ])
            ->add($autoresponder . '_message', TextareaType::class, [
                'label' => __('Message'),
                'required' => false,
            ])
            ->add('submit', SubmitType::class);

        $builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event) use ($autoresponder) {
            $submittedData = $event->getData();
            if (empty($submittedData[$autoresponder . '_subject']) xor empty($submittedData[$autoresponder . '_message'])) {
                // This will be a global error message on the form, not on any specific field
                throw new TransformationFailedException(
                    'message and subject must be set',
                    0, // code
                    null, // previous
                    __('Both message and subject must be set for the automatic email to work. Clear both to stop sending automatic emails.'), // user message
                );
            }
        });

        $form = $builder->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->model->update($iter);
            return $this->redirectToRoute('mailing_lists.update', ['id' => $iter->get_id()]);
        }

        return $this->render('mailing_lists/autoresponder_form.html.twig', [
            'iter' => $iter,
            'autoresponder' => $autoresponder,
            'form' => $form,
        ]);
    }

    #[Route('/mailing_lists/{id<\d+>}/archive', name: 'mailing_lists.archive.list', methods: ['GET'])]
    public function archiveList(int $id): Response
    {
        $list = $this->model->get_iter($id);

        if (!$this->policy->userCanReadArchive($list))
            throw new UnauthorizedException('You cannot read the archives of this mailing list.');

        $messages = $this->db->getModel('DataModelMailinglistArchive')->get_for_list($list);

        return $this->render('mailing_lists/archive_list.html.twig', [
            'list' => $list,
            'messages' => $messages,
        ]);
    }

    #[Route('/mailing_lists/{id<\d+>}/archive/{message_id<\d+>}', name: 'mailing_lists.archive.single', methods: ['GET'])]
    public function archiveSingle(int $id, int $message_id): Response
    {
        $list = $this->model->get_iter($id);

        if (!$this->policy->userCanReadArchive($list))
            throw new UnauthorizedException('You cannot read the archives of this mailing list.');

        $message = $this->db->getModel('DataModelMailinglistArchive')->get_iter($message_id);

        $html_body = null;
        $text_body = null;
        $subject = null;
        $error = null;

        try {
            $parsed = MessagePart::parse_text($message['bericht']);

            $subject = $parsed->header('Subject');

            foreach ($parsed->textParts() as $part) {
                if (\preg_match('/^text\/html\b/i', $part->header('Content-Type')))
                    $html_body = $part->body();
                else
                    $text_body = $part->body();
            }
        } catch (\Exception $e) {
            $error = $e;
        }

        return $this->render('mailing_lists/archive_single.html.twig', compact('list', 'message', 'subject', 'html_body', 'text_body', 'error'));
    }

    public function markup(int|string $id, string $referrer, Authentication $auth): Response
    {
        if (\is_string($id))
            $list = $this->model->get_iter_by_address($id);
        else
            $list = $this->model->get_iter($id);

        $member = $auth->identity->member();
        $subscriptionModel = $this->db->getModel('DataModelMailinglistSubscription');

        $builder = $this->createFormBuilder(null, ['csrf_token_id' => 'mailinglist_subscription_' . $list['id']]);

        if ($member && $subscriptionModel->is_subscribed($list, $member) && $this->policy->userCanUnsubscribe($list))
            $builder->add('do_unsubscribe', SubmitType::class, ['label' => __('Unsubscribe')]);
        elseif ($member && $this->policy->userCanSubscribe($list))
            $builder->add('do_subscribe', SubmitType::class, ['label' => __('Subscribe')]);

        $form = $builder->getForm();

        return $this->render('mailing_lists/_markup.html.twig', [
            'list' => $list,
            'referrer' => $referrer,
            'form' => $form,
        ]);
    }

    /**
     * TODO SFY: Legacy PHP routes
     * TODO SFY: Do we need to support more routes?
     * TODO SFY: the conflict with mailing_lists.list is annoying.
     */
     #[Route('/mailing_lists', methods: ['GET'], priority: 2)]
    public function legacy(
        #[MapQueryParameter] ?string $abonnement_id = null,
    ) {
        if (isset($abonnement_id))
            return $this->redirectToRoute('mailing_lists.subscription.unsubscribe', ['id' => $abonnement_id], Response::HTTP_MOVED_PERMANENTLY);
        return $this->forward('App\Controller\MailingListsController::list');
    }
}
