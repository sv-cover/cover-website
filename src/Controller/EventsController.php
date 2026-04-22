<?php

namespace App\Controller;

use App\DataIter\DataIterAgenda;
use App\DataIter\DataIterCommissie;
use App\DataModel\DataModelAgenda;
use App\DataModel\DataModelCommissie;
use App\DataModel\DataModelSession;
use App\Exception\UnauthorizedException;
use App\Form\DataTransformer\IntToBooleanTransformer;
use App\Form\EventType;
use App\Markup\Markup;
use App\Legacy\Authentication\Authentication;
use App\Legacy\Policy\Policy;
use App\Utils\WebCal;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class EventsController extends AbstractController
{
    public function __construct(
        private DataModelAgenda $model,
        private Policy $policy,
    ){
    }

    /**
     * Generate calendar navigation.
     * Note: id uses a regular dash, display uses an en-dash!
     */
    private function getNavigation(?int $year): array
    {
        $navigation = [];

        $currentYear = intval(
            time() < mktime(0, 0, 0, 9, 1, date('Y'))
            ? date('Y') - 1
            : date('Y')
        );

        if ($year !== null)
            $navigation['current'] = [
                'id' => sprintf('%d-%d', $year, $year + 1),
                'display' => sprintf('%d−%d', $year, $year + 1),
                'display_short' => sprintf('%d/%d', $year, ($year % 100) + 1),
            ];

        if ($year && $year > 2003)
            $navigation['previous'] = [
                'id' => sprintf('%d-%d', $year - 1, $year),
                'display' => sprintf('%d−%d', $year - 1, $year),
                'display_short' => sprintf('%02d/%02d', ($year % 100) - 1, $year % 100),
            ];
        else
            $navigation['previous'] = [
                'id' => sprintf('%d-%d', $currentYear, $currentYear + 1),
                'display' => __('Archive'),
            ];

        if ($year == $currentYear)
            $navigation['next'] = [
                'id' => null,
                'display' => __('Upcoming events'),
                'display_short' => __('Upcoming'),
            ];
        elseif ($year && $year < $currentYear)
            $navigation['next'] = [
                'id' => sprintf('%d-%d', $year + 1, $year + 2),
                'display' => sprintf('%d−%d', $year + 1, $year + 2),
                'display_short' => sprintf('%02d/%02d', ($year % 100) + 1, ($year % 100) + 2),
            ];

        return $navigation;
    }

    /**
     * Renders upcoming events or events for a specific academic year
     * OK, this year thing is a bit silly. But:
     * 1. it makes urls look nice
     * 2. it makes urls more intuitive
     * 3. it deconflicts route matching with events.single
     */
    #[Route('/events/{year<\d{4}-\d{4}>}', name: 'events.list', methods: ['GET'])]
    public function list(
        Authentication $auth,
        DataModelSession $sessionModel,
        ?string $year = null
    ): Response|RedirectResponse
    {
        $year = $year ? intval(substr($year, 0, 4)) : null;

        // Don't have data from before 2003, can't be too far into the future.
        // Redirect to upcoming events instead
        if ($year && ($year < 2003 || $year > date('Y') + 2))
            return $this->redirectToRoute('events.list');

        if ($year === null) {
            $iters = $this->model->get_agendapunten();
        } else {
            $from = sprintf('%d-09-01', $year);
            $till = sprintf('%d-08-31', $year + 1);

            $iters = $this->model->get($from, $till, true);
        }

        // Apply policy
        $iters = array_filter($iters, [$this->policy, 'userCanRead']);

        return $this->render('events/list.html.twig', [
            'iters' => $iters,
            'navigation' => $this->getNavigation($year),
            'calendar_session' => $auth->loggedIn ? $sessionModel->getForApplication($auth->identity->get('id'), 'calendar') : null,
        ]);
    }

    #[Route('/events/create', name: 'events.create', methods: ['GET', 'POST'])]
    public function create(
        Authentication $auth,
        DataModelCommissie $committeeModel,
        MailerInterface $mailer,
        Request $request
    ): Response|RedirectResponse
    {
        $iter = $this->model->new_iter();

        if (!$this->policy->userCanCreate($iter))
            throw new UnauthorizedException('You are not allowed to create events.');

        $form = $this->createForm(EventType::class, $iter, ['mapped' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Some things break without end date (tot), so set end date to start date (van)
            if (empty($iter['tot']))
                $iter['tot'] = $iter['van'];

            $this->model->propose_insert($iter);

            $this->addFlash('notice', __('The new event is now waiting for approval. Once the board has accepted the event, it will be published on the website.'));

            $email = (new TemplatedEmail())
                ->to($this->getParameter('app.email_board'))
                ->subject('New event ' . $iter['kop'])
                ->textTemplate('emails/event_created.txt.twig')
                ->context([
                    'member_name' => $auth->getIdentity()->member()->get_full_name(ignorePrivacy: true),
                    'committee_name' => $committeeModel->get_naam($iter['committee_id']),
                    'event' => $iter,
                ])
            ;
            $mailer->send($email);

            return $this->redirectToRoute('events.single', ['id' => $iter->get_id()]);
        }

        return $this->render('events/form.html.twig', [
            'iter' => $iter,
            'form' => $form,
        ]);
    }

    // TODO: link events by slug, e.g. 2024-11-11-general-assembly
    #[Route('/events/{id<\d+>}', name: 'events.single', methods: ['GET'])]
    public function single(int $id): Response
    {
        $iter = $this->model->get_iter($id);

        if (!$this->policy->userCanRead($iter))
            throw new UnauthorizedException('You are not allowed to read this event.');

        return $this->render('events/single.html.twig', ['iter' => $iter]);
    }

    #[Route('/events/{id<\d+>}/update', name: 'events.update', methods: ['GET', 'POST'])]
    public function update(
        Authentication $auth,
        DataModelCommissie $committeeModel,
        MailerInterface $mailer,
        Request $request,
        int $id
    ): Response|RedirectResponse
    {
        $iter = $this->model->get_iter($id);

        if (!$this->policy->userCanUpdate($iter))
            throw new UnauthorizedException('You are not allowed to edit this event.');

        $orig = DataIterAgenda::from_iter($iter);

        $form = $this->createForm(EventType::class, $iter, ['mapped' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // We could set $skip_confirmation in one statement, but I find this more readable
            $skip_confirmation = false;

            // If you update the facebook-id, description, image or location, no need to reconfirm.
            if (!\array_diff(\array_keys($iter->get_updated_fields($orig)), ['facebook_id', 'beschrijving', 'image_url', 'locatie']))
                $skip_confirmation = true;

            // Unless the event was in the past, then we need confirmation as we most likely shouldn't be changing things anyway
            if ((empty($orig['tot_datetime']) && $orig['van_datetime'] < new \DateTime()) || $orig['tot_datetime'] < new \DateTime())
                $skip_confirmation = false;

            // Previous exists and there is no need to let the board confirm it
            if ($skip_confirmation) {
                $this->model->update($iter);
                $this->addFlash('notice', __("The changes you've made to this event have been published."));
            } else {
                $proposalId = $this->model->propose_update($iter);

                $this->addFlash('notice', __('The changes to the event are waiting for approval. Once the board has accepted the changes, they will be published on the website.'));

                $email = (new TemplatedEmail())
                    ->to($this->getParameter('app.email_board'))
                    ->subject('Updated event: ' . $iter['kop'] . ($iter->get('kop') != $orig->get('kop') ? ' was ' . $orig->get('kop') : ''))
                    ->textTemplate('emails/event_updated.txt.twig')
                    ->context([
                        'proposal_id' => $proposalId,
                        'member_name' => $auth->getIdentity()->member()->get_full_name(ignorePrivacy: true),
                        'committee_name' => $committeeModel->get_naam($iter['committee_id']),
                        'event' => $iter,
                    ])
                ;
                $mailer->send($email);
            }

            return $this->redirectToRoute('events.single', ['id' => $iter->get_id()]);
        }

        return $this->render('events/form.html.twig', [
            'iter' => $iter,
            'form' => $form,
        ]);
    }

    #[Route('/events/{id<\d+>}/delete', name: 'events.delete', methods: ['GET', 'POST'])]
    public function delete(Request $request, int $id): Response|RedirectResponse
    {
        $iter = $this->model->get_iter($id);

        if (!$this->policy->userCanDelete($iter))
            throw new UnauthorizedException('You are not allowed to delete this event.');

        $form = $this->createFormBuilder($iter)
            ->add('submit', SubmitType::class, ['label' => __('Delete'), 'color' => 'danger'])
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->model->delete($iter);
            return $this->redirectToRoute('events.list');
        }

        return $this->render('events/confirm_delete.html.twig', [
            'iter' => $iter,
            'form' => $form,
        ]);
    }

    #[Route('/events/moderate', name: 'events.moderate', methods: ['GET'])]
    public function moderate(): Response
    {
        $iters = array_filter($this->model->get_proposed(), [$this->policy, 'userCanModerate']);

        return $this->render('events/moderate.html.twig', [
            'iters' => $iters,
        ]);
    }

    #[Route('/events/{id<\d+>}/accept', name: 'events.accept', methods: ['GET', 'POST'])]
    public function accept(Request $request, int $id): Response|RedirectResponse
    {
        $iter = $this->model->get_iter($id);

        if (!$this->policy->userCanModerate($iter))
            throw new UnauthorizedException();

        $builder = $this->createFormBuilder($iter, ['csrf_token_id' => 'event_accept_' . $iter['id']])
            ->add('submit', SubmitType::class, ['label' => 'Accept event']);

        // Can only override private and extern for new events.
        if ($iter['replacement_for'] === 0) {
            $builder->add('private', CheckboxType::class, [
                'label'    => __('Only visible to members'),
                'required' => false,
            ]);
            $builder->add('extern', CheckboxType::class, [
                'label'    => __('This event is not organised by Cover'),
                'required' => false,
            ]);
            $builder->get('private')->addModelTransformer(new IntToBooleanTransformer());
            $builder->get('extern')->addModelTransformer(new IntToBooleanTransformer());
        }

        $form = $builder->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid())
            $this->model->accept_proposal($iter);

        return $this->redirectToRoute('events.moderate');
    }

    #[Route('/events/{id<\d+>}/reject', name: 'events.reject', methods: ['GET', 'POST'])]
    public function reject(
        Authentication $auth,
        Request $request,
        DataModelCommissie $committeeModel,
        MailerInterface $mailer,
        int $id
    ): Response|RedirectResponse
    {
        $iter = $this->model->get_iter($id);

        if (!$this->policy->userCanModerate($iter))
            throw new UnauthorizedException();

        $form = $this->createFormBuilder()
            ->add('reason', TextareaType::class, [
                'label' => __('Reason for rejection'),
                'required' => false,
                'help' => __('This will be emailed to the committee.'),
            ])
            ->add('submit', SubmitType::class, ['label' => 'Reject event'])
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /* Delete event proposal and inform the event owner */
            $this->model->reject_proposal($iter);

            $email = (new TemplatedEmail())
                ->to($committeeModel->get_email($iter['committee_id']))
                ->replyTo($this->getParameter('app.email_board'))
                ->subject('Rejected event: ' . $iter['kop'])
                ->textTemplate('emails/event_rejected.txt.twig')
                ->context([
                    'member_name' => $auth->getIdentity()->member()->get_full_name(ignorePrivacy: true),
                    'event' => $iter,
                    'reason' => $form->get('reason')->getData(),
                ])
            ;
            $mailer->send($email);

            $this->addFlash('notice', sprintf(
                __('The %s has been notified that their event has been rejected.'),
                $committeeModel->get_naam($iter['committee_id'])
            ));
            return $this->redirectToRoute('events.moderate');
        }

        return $this->render('events/confirm_reject.html.twig', [
            'iter' => $iter,
            'form' => $form,
        ]);
    }

    #[Route('/events/locations', name: 'events.locations', methods: ['GET'])]
    public function locations(
        #[MapQueryParameter] string $search,
        #[MapQueryParameter] ?int $limit = 100
    ): Response
    {
        $locations = $this->model->find_locations($search, $limit);

        return $this->json($locations);
    }

    #[Route('/events/slide', name: 'events.slide', methods: ['GET'])]
    public function slide(): Response
    {
        $iters = $this->model->get_agendapunten();
        $iters = array_filter($iters, [$this->policy, 'userCanRead']);
        return $this->render('events/slide.html.twig', ['iters' => $iters]);
    }

    #[Route('/events/subscribe', name: 'events.subscribe', methods: ['GET'])]
    public function subscribe(
        Authentication $auth,
        DataModelSession $sessionModel,
    ): Response
    {
        return $this->render('events/subscribe.html.twig', [
            'calendar_session' => $auth->loggedIn ? $sessionModel->getForApplication($auth->identity->get('id'), 'calendar') : null,
        ]);
    }

    // If debugging is hard, temporarily change schemes to 'http'
    #[Route('/events/webcal', name: 'events.webcal', methods: ['GET'], schemes: 'webcal')]
    public function webcal(Markup $markup): Response
    {
        $cal = new WebCal\Calendar(
            name: 'Cover',
            description: __('All activities of study association Cover'),
        );

        $fromdate = new \DateTime();
        $fromdate = $fromdate->modify('-1 year')->format('Y-m-d');

        $timezone = new \DateTimeZone('Europe/Amsterdam');

        $events = $this->model->get($fromdate, null, true);

        foreach ($events as $event) {
            if (!$this->policy->userCanRead($event))
                continue;

            $webcalEvent = new WebCal\Event(
                start: new \DateTime($event['van'], $timezone),
                uid: $event->get_id() . '@svcover.nl',
                summary: (
                    $event['extern']
                    ? $event['kop']
                    : sprintf('%s: %s', $event['committee__naam'], $event['kop'])
                ),
                description: $markup->strip($event['beschrijving']),
                location: $event['locatie'],
                url: $this->generateUrl('events.single', ['id' => $event->get_id()], UrlGeneratorInterface::ABSOLUTE_URL),
            );

            if (empty($event['tot']) || $event['van'] == $event['tot']) {
                $webcalEvent->end = new \DateTime($event['van'], $timezone);
                $webcalEvent->end->modify('+ 2 hour');
            } else {
                $webcalEvent->end = new \DateTime($event['tot'], $timezone);
            }

            $cal->add($webcalEvent);
        }

        $externalUrl = $this->getParameter('app.external_webcal_url');

        if ($externalUrl) {
            try {
                $external = file_get_contents($externalUrl);
                $cal->inject($external);
            } catch (\Exception $exception) {
                // if something goes wrong, just don't merge with external agenda
                \Sentry\captureException($exception);
            }
        }

        return new Response(
            $cal->export(),
            Response::HTTP_OK,
            $cal->getHeaders('cover.ics')
        );
    }

    public function homepage(): Response
    {
        $iters = $this->model->get_agendapunten();
        $iters = array_filter($iters, [$this->policy, 'userCanRead']);

        return $this->render('events/_homepage.html.twig', [
            'iters' => $iters,
        ]);
    }

    public function committeePage(DataIterCommissie $committee): Response
    {
        $iters = $this->model->get_agendapunten();

        $iters = array_filter($iters, fn($e): bool => (
            $e['committee_id'] == $committee['id'] && $this->policy->userCanRead($e)
        ));

        return $this->render('events/_committee_list.html.twig', [
            'committee' => $committee,
            'iters' => $iters,
        ]);
    }

    /**
     * TODO: Phase out legacy webcal urls. But phase out legacy Auth first.
     */
     #[Route('/agenda.php', methods: ['GET'], schemes: ['http', 'https', 'webcal'])]
     #[Route('/calendar', methods: ['GET'], schemes: ['http', 'https', 'webcal'])]
    public function legacy(
        #[MapQueryParameter] ?string $format = null,
    ) {
        if ($format == 'webcal')
            return $this->forward('App\Controller\EventsController::webcal');
        throw $this->createNotFoundException('Page not found');
    }
}
