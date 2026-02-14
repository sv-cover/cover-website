<?php

namespace App\Controller;

use App\DataModel\DataModelSignUpEntry;
use App\DataModel\DataModelSignUpForm;
use App\DataIter\DataIterAgenda;
use App\DataIter\DataIterSignupForm;
use App\Exception\UnauthorizedException;
use App\Form\SignUpFieldType;
use App\Legacy\Authentication\Authentication;
use App\Legacy\Policy\Policy;
use App\SignUp\SignUpFormManager;
use App\Utils\UrlUtils;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Attribute\Route;

class SignUpEntriesController extends AbstractController
{
    public function __construct(
        private DataModelSignUpEntry $entryModel,
        private DataModelSignUpForm $formModel,
        private SignUpFormManager $manager,
        private Policy $policy,
    ) {
    }

    public function eventPage(Authentication $auth, DataIterAgenda $event): Response
    {
        if (!$auth->loggedIn)
            return $this->render('sign_ups/entries/_event_page_login.html.twig', [
                'event' => $event,
            ]);

        $forms = array_filter($event['signup_forms'], [$this->policy, 'userCanSignup']);

        return $this->render('sign_ups/entries/_event_page.html.twig', [
            'forms' => $forms,
            'event' => $event,
        ]);
    }

    #[Route('/sign_up/{form_id<\d+>}/entries', name: 'sign_up_entries.list', methods: ['GET'])]
    public function list(int $form_id): Response
    {
        $form = $this->formModel->get_iter($form_id);

        if (!$this->policy->userCanRead($form))
            throw new UnauthorizedException('You are not allowed to see the sign ups for this form.');

        return $this->render('sign_ups/entries/list.html.twig', [
            'form' => $form,
        ]);
    }

    private function streamCsv(DataIterSignupForm $form): void
    {
        // Add Unicode byte order marker for Excel
        echo chr(239) . chr(187) . chr(191);

        $entries = \array_filter($form['entries'], [$this->policy, 'userCanRead']);

        $headers = [];
        foreach ($form->get_fields() as $field)
            $headers = \array_merge($headers, $this->manager->getColumnLabels($field));
        $headers['signed-up-on'] = 'Signed up on';

        if (count($entries) === 0)
            return;

        $out = fopen('php://output', 'w');

        fputcsv($out, $headers);
        flush();

        foreach ($entries as $entry) {
            fputcsv($out, $this->manager->exportEntry($entry));
            flush();
        }
    }

    #[Route('/sign_up/{form_id<\d+>}/entries/export', name: 'sign_up_entries.export', methods: ['GET'])]
    public function export(int $form_id): StreamedResponse
    {
        $form = $this->formModel->get_iter($form_id);

        if (!$this->policy->userCanRead($form))
            throw new UnauthorizedException('You are not allowed to see the sign ups for this form.');

        $filename = sprintf('signup-form-%d-%s.csv', $form['id'], date('ymd-his'));

        $response = new StreamedResponse(function() use ($form): void {
            $this->streamCsv($form);
        });
        $response->headers->set('Content-Description:', 'File Transfer');
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $response->headers->set('X-Accel-Buffering', 'no');
        return $response;
    }

    #[Route('/sign_up/{form_id<\d+>}/entries/create', name: 'sign_up_entries.create', methods: ['GET', 'POST'])]
    public function create(
        Authentication $auth,
        MailerInterface $mailer,
        Request $request,
        UrlUtils $urlUtils,
        int $form_id,
        #[MapQueryParameter] bool $prefill = true,
        #[MapQueryParameter] string $context = 'standalone',
    ): Response|RedirectResponse
    {
        $iter = $this->formModel->get_iter($form_id);

        if (!$this->policy->userCanSignup($iter))
            throw new UnauthorizedException('You cannot access this form.');

        $entry = $iter->new_entry($prefill);

        if (!$this->policy->userCanCreate($entry))
            throw new UnauthorizedException('You cannot create new entries for this form.');

        $form = $this->manager->getForm($entry);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!empty($form->get('member_id')->getData()))
                $entry['member_id'] = (int) $form->get('member_id')->getData();

            $this->manager->processEntry($entry, $form);
            $this->entryModel->insert($entry);

            try {
                if (!empty($entry['member_id']) && $iter['agenda_item']) {
                    $email = (new TemplatedEmail())
                        ->to($entry['member']['email'])
                        ->replyTo($entry['form']['agenda_item']['committee']['email'])
                        ->subject("[Cover] You've signed up for " . $entry['form']['agenda_item']['kop'])
                        ->htmlTemplate('emails/sign_up_confirmation.html.twig')
                        ->textTemplate('emails/sign_up_confirmation.txt.twig')
                        ->context([
                            'entry' => $entry,
                        ])
                    ;
                    $mailer->send($email);
                }
            } catch (\Exception $exception) {
                throw $exception;
                // Catch it, but it is not important for the rest of the process.
                \Sentry\captureException($exception);
            }

            if (!empty($form->get('return_path')->getData()))
                return $this->redirect($urlUtils->validateRedirect(
                    $form->get('return_path')->getData()
                ));

            // Redirect admins back to the entry index
            if ($this->policy->userCanUpdate($iter))
                return $this->redirectToRoute('sign_up_entries.list', [
                    'form_id' => $form_id,
                ]);

            return $this->render('sign_ups/entries/form_success.html.twig', [
                'iter' => $iter,
                'entry' => $entry,
                'context' => $context,
            ]);
        }

        return $this->render('sign_ups/entries/form.html.twig', [
            'iter' => $iter,
            'form' => $form,
            'entry' => $entry,
            'context' => $context,
        ]);
    }

    #[Route('/sign_up/entries/{id<\d+>}', name: 'sign_up_entries.single', methods: ['GET'])]
    public function single(int $form_id, int $id): Response
    {
        return $this->redirectToRoute('sign_up_entries.update', [
            'form_id' => $form_id,
            'id' => $id,
        ]);
    }

    #[Route('/sign_up/entries/{id<\d+>}/update', name: 'sign_up_entries.update', methods: ['GET', 'POST'])]
    public function update(
        Request $request,
        int $id,
        #[MapQueryParameter] string $context = 'standalone',
    ): Response|RedirectResponse
    {
        $entry = $this->entryModel->get_iter($id);
        $iter = $entry['form'];

        if (!$this->policy->userCanSignup($iter))
            throw new UnauthorizedException('You cannot access this form.');

        if (!$this->policy->userCanUpdate($entry))
            throw new UnauthorizedException('You cannot access this entry.');

        $form = $this->manager->getForm($entry);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->manager->processEntry($entry, $form);
            $this->entryModel->update($entry);

            // Redirect admins back to the entry index
            if (!$this->policy->userCanUpdate($iter))
            {
                $this->addFlash('signup_entry_updated', __('Your signup has been updated'));
                return $this->render('homepage/homepage.html.twig');
                // return $this->redirectToRoute('sign_up_entries.list', [
                //     'form_id' => $entry['form_id'],
                // ]);
            }

            return $this->render('sign_ups/entries/form_success.html.twig', [
                'iter' => $iter,
                'entry' => $entry,
                'context' => $context,
            ]);
        }

        return $this->render('sign_ups/entries/form.html.twig', [
            'iter' => $iter,
            'form' => $form,
            'entry' => $entry,
            'context' => $context,
        ]);
    }

    #[Route('/sign_up/{form_id<\d+>}/entries/delete', name: 'sign_up_entries.delete', methods: ['GET', 'POST'])]
    public function delete(Request $request, int $form_id): Response|RedirectResponse
    {
        $iter = $this->formModel->get_iter($form_id);

        if (!$this->policy->userCanDelete($iter))
            throw new UnauthorizedException('You are not allowed to delete this entry.');

        $form = $this->createFormBuilder(null, ['csrf_token_id' => 'form_' . $iter['id'] . '_delete_entries'])
            ->add('entries', ChoiceType::class, [
                'expanded' => true,
                'multiple' => true,
                'choices' => $iter->get_entries(),
                'choice_label' => fn($entity) => $entity['id'],
                'choice_value' => fn($entity) => $entity['id'],
            ])
            ->add('submit', SubmitType::class)
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid())
            foreach ($form->get('entries')->getData() as $entry)
                if ($this->policy->userCanDelete($entry))
                    $this->entryModel->delete($entry);

        return $this->redirectToRoute('sign_up_entries.list', [
            'form_id' => $iter->get_id(),
        ]);
    }
}
