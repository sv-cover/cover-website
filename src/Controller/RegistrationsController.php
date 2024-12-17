<?php

namespace App\Controller;

use App\DataModel\DataModelMember;
use App\DataModel\DataModelPage;
use App\Exception\NotFoundException;
use App\Exception\UnauthorizedException;
use App\Form\RegistrationType;
use App\Legacy\Database\DatabasePDO;
use App\Service\Authentication;
use App\Service\Secretary;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\String\ByteString;

class RegistrationsController extends AbstractController
{
    public function __construct(
        private DatabasePDO $db,
        private DataModelMember $model,
        private MailerInterface $mailer,
    ) {
    }

    private function getDataForToken(string $token): array
    {
        $query = <<<SQL
            SELECT data
              FROM registrations
             WHERE confirmation_code = :confirmation_code
               AND confirmed_on IS NULL;
        SQL;
        $result = $this->db->query_value($query, ['confirmation_code' => $token]);

        if ($result === null)
            throw $this->createNotFoundException('Can’t find registration code');

        return \json_decode($result, true);
    }

    private function sendConfirmationMail(string $token): void
    {
        $query = <<<SQL
            SELECT data
              FROM registrations
             WHERE confirmation_code = :confirmation_code
               AND confirmed_on IS NULL;
        SQL;
        $result = $this->db->query_value($query, ['confirmation_code' => $token]);

        if ($result === null)
            throw $this->createNotFoundException('Can’t find registration code');

        $data = \array_merge(\json_decode($result, true), [
            'link' => $this->generateUrl('registrations.confirm_email', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL),
        ]);

        $email = (new TemplatedEmail())
            ->to($data['email_address'])
            ->replyTo(new Address('secretary@svcover.nl', 'Cover Secretary'))
            ->subject("[Cover] Confirm your membership application")
            ->htmlTemplate('emails/join_confirm_email.html.twig')
            ->textTemplate('emails/join_confirm_email.txt.twig')
            ->context($data)
        ;
        $this->mailer->send($email);
    }

    private function pushToMailbox(string $token): void
    {
        $query = <<<SQL
            SELECT data
              FROM registrations
             WHERE confirmation_code = :confirmation_code
               AND confirmed_on IS NULL;
        SQL;
        $result = $this->db->query_value($query, ['confirmation_code' => $token]);

        if ($result === null)
            throw $this->createNotFoundException('Can’t find registration code');

        $data = \json_decode($result, true);

        $data['confirmation_code'] = $token;
        $name = $data['first_name'] . (!empty($data['family_name_preposition']) ? ' ' . $data['family_name_preposition'] : '') . ' ' . $data['family_name'];


        $email = (new TemplatedEmail())
            ->to('administratie@svcover.nl')
            ->replyTo(new Address('secretary@svcover.nl', 'Cover Secretary'))
            ->subject("Membership application $name")
            ->textTemplate('emails/join_administratie.txt.twig')
            ->context($data)
        ;
        $this->mailer->send($email);
    }

    private function pushToSecretary(Secretary $secretary, string $token): void
    {
        $query = <<<SQL
            SELECT data
              FROM registrations
             WHERE confirmation_code = :confirmation_code;
        SQL;
        $result = $this->db->query_value($query, ['confirmation_code' => $token]);

        if ($result === null)
            throw $this->createNotFoundException('Can’t find registration code');

        $data = json_decode($result, true);

        $response = $secretary->createPerson($data);
        $this->db->delete('registrations', sprintf("confirmation_code = '%s'", $this->db->escape_string($token)));
    }

    #[Route('/join', name: 'registrations.join', methods: ['GET', 'POST'])]
    public function join(DataModelPage $pageModel, Request $request): Response|RedirectResponse
    {
        $defaults = [
            'membership_study_phase' => 'b',
            'membership_year_of_enrollment' => (
                \time() < \mktime(0, 0, 0, 7, 1, \date('Y'))
                ? \date('Y') - 1
                : \date('Y')
            ),
        ];
        $form = $this->createForm(RegistrationType::class, $defaults, ['mapped' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            // Test whether email is already used
            // (already a member? Or previous member?)
            try {
                $this->model->get_from_email($data['email_address']);
                return $this->render('registrations/join_known_member.html.twig');
            } catch (NotFoundException $e) {
                // All clear :)
            }

            /* Mailing is opt-out. We can do this, because assocations are allowed to contact their members
            about activities without consent, as long as it is non-commercial. See this link (at nieuwsbrief)
            https://www.declercq.com/kennisblog/wat-betekent-de-avg-voor-verenigingen/
            For now, secretary subscribes members to this one, so send option_mailing to secretary…
            */
            // TODO: Make mailing officially opt-out, with migration to explicitly opt-out everyone who didn't opt-in first…
            $data['option_mailing'] = true;

            // Same as email confirmation / password reset
            $token = ByteString::fromRandom(40)->toString();

            // Store this info temporarily in the database and send a confirmation mail
            $this->db->insert('registrations', [
                'confirmation_code' => $token,
                'data' => \json_encode($data),
            ]);
            $this->sendConfirmationMail($token);

            // Redirect to prevent accidental repeated submissions
            return $this->redirectToRoute('registrations.join_success');
        }

        return $this->render('registrations/join_form.html.twig', [
            'terms' => $pageModel->get_iter_from_title('Voorwaarden aanmelden'),
            'form' => $form,
        ]);
    }

    #[Route('/join/submitted', name: 'registrations.join_success', methods: ['GET'])]
    public function joinSuccess(Request $request): Response
    {
        return $this->render('registrations/join_success.html.twig');
    }

    #[Route('/join/confirm_email', name: 'registrations.confirm_email', methods: ['GET', 'POST'])]
    public function confirmEmail(
        #[MapQueryParameter] string $token,
        Request $request,
        Secretary $secretary,
    ): Response|RedirectResponse
    {
        try {
            // First, send a mail to administratie@svcover.nl for archiving purposes
            $this->pushToMailbox($token);

            // If that worked out right, we can mark this registration as confirmed.
            $this->db->update('registrations',
                ['confirmed_on' => date('Y-m-d H:i:s')],
                sprintf("confirmation_code = '%s'", $this->db->escape_string($token)));

            try {
                // Try to add the member to Secretary. If this works out correctly
                // the registration will be deleted (and Secretary will add the
                // member to the leden table through the API).
                $this->pushToSecretary($secretary, $token);
            } catch (\Exception|\Error $e) {
                // Well, that didn't work out. Report the error to everybody.
                // The registration will be marked as confirmed, but not deleted
                // so one can try again later when Secretary is available again.

                \Sentry\captureException($e);

                $email = (new Email())
                    ->to('webcie@svcover.nl')
                    ->subject('Error during membership application')
                    ->text(
                        "Something went wrong while trying to add a new member to Secretary.\n" .
                        "In case it helps, the confirmation code was: " . $token . "\n" .
                        "Maybe the website error log for " . \date('Y-m-d H:i:s') . " will provide more insight. Or this:\n\n"
                        . $e->getMessage() . "\n"
                        . $e->getTraceAsString()
                    )
                ;
                $this->mailer->send($email);

                $email = (new Email())
                    ->to('secretary@svcover.nl')
                    ->subject('Error during membership application (member not added to Secretary)')
                    ->text(
                        "Something went wrong while trying to add a new member to Secretary. The AC/DCee has been informed about this.\n" .
                        "You can find the application in the administratie@svcover.nl mailbox.\n" .
                        "In case it helps, the confirmation code was: " . $token
                    )
                ;
                $this->mailer->send($email);
            }
            // Redirect to prevent accidental repeated submissions
            return $this->redirectToRoute('registrations.confirm_email_success');
        } catch (NotFoundException|NotFoundHttpException $e) {
            return $this->render('registrations/confirm_email_not_found.html.twig');
        }
    }

    #[Route('/join/confirm_email/success', name: 'registrations.confirm_email_success', methods: ['GET'])]
    public function confirmEmailSuccess(Request $request): Response
    {
        return $this->render('registrations/confirm_email_success.html.twig');
    }

    #[Route('/registrations', name: 'registrations.pending.list', methods: ['GET', 'POST'])]
    public function pendingList(Authentication $auth, Request $request, Secretary $secretary): Response
    {
        if (!$auth->getIdentity()->member_in_committee(COMMISSIE_BESTUUR) &&
            !$auth->getIdentity()->member_in_committee(COMMISSIE_KANDIBESTUUR) &&
            !$auth->getIdentity()->member_in_committee(COMMISSIE_EASY))
            throw new UnauthorizedException();

        $registrationsQuery = <<<SQL
            SELECT
                confirmation_code,
                data,
                registerd_on as registered_on,
                confirmed_on
            FROM
                registrations
            ORDER BY
                registerd_on DESC
        SQL;

        $registrations = $this->db->query($registrationsQuery);

        $form = $this->createFormBuilder()
            ->add('registration', ChoiceType::class, [
                'expanded' => true,
                'multiple' => true,
                'choices' => \array_map(fn($r) => (object) $r, $registrations),
                'choice_label' => fn($e) => $e->confirmation_code ?? '',
                'choice_value' => fn($e) => $e->confirmation_code ?? '',
            ])
            ->add('push_to_secretary', SubmitType::class)
            ->add('resend_confirmation', SubmitType::class)
            ->add('delete', SubmitType::class)
            ->getForm();
        $form->handleRequest($request);

        $message = null;
        if ($form->isSubmitted() && $form->isValid()) {
            if ($form->get('push_to_secretary')->isClicked()) {
                $success = 0;
                foreach ($form->get('registration')->getData() as $registration) {
                    try {
                        $this->pushToSecretary($secretary, $registration->confirmation_code);
                        $success++;
                    } catch (\Exception $exception) {
                        \Sentry\captureException($exception);
                    }
                }
                $message = sprintf(
                    'Added %d out of %d registrations to Secretary',
                    $success,
                    count($form->get('registration')->getData())
                );
            } elseif ($form->get('resend_confirmation')->isClicked()) {
                $success = 0;
                foreach ($form->get('registration')->getData() as $registration) {
                    try {
                        $this->sendConfirmationMail($registration->confirmation_code);
                        $success++;
                    } catch (\Exception $exception) {
                        // Probably already confirmed
                    }
                }
                $message = sprintf(
                    'Resent %d out of %d confirmation emails',
                    $success,
                    count($form->get('registration')->getData())
                );
            } elseif ($form->get('delete')->isClicked() && count($form->get('registration')->getData()) > 0) {
                $ids = array_map(fn($r) => $r->confirmation_code, $form->get('registration')->getData());
                $rows = $this->db->execute(\sprintf(
                    "DELETE FROM registrations WHERE confirmation_code IN (%s)",
                    $this->db->quote_value($ids)
                ));
                $message = \sprintf('Deleted %d registrations', $rows);
            }
            // Query registrations again. Things might have changed.
            $registrations = $this->db->query($registrationsQuery);
        }

        foreach ($registrations as &$registration)
            $registration['data'] = \json_decode($registration['data'], true);

        return $this->render('registrations/pending_list.html.twig', [
            'registrations' => $registrations,
            'message' => $message,
            'form' => $form,
        ]);
    }

    #[Route('/registrations/{token}', name: 'registrations.pending.update', methods: ['GET', 'POST'])]
    public function pendingUpdate(string $token, Authentication $auth, Request $request): Response|RedirectResponse
    {
        if (!$auth->getIdentity()->member_in_committee(COMMISSIE_BESTUUR) &&
            !$auth->getIdentity()->member_in_committee(COMMISSIE_KANDIBESTUUR) &&
            !$auth->getIdentity()->member_in_committee(COMMISSIE_EASY))
            throw new UnauthorizedException();

        // Load data
        $row = $this->db->query_first(\sprintf("SELECT * FROM registrations WHERE confirmation_code = '%s'",
            $this->db->escape_string($token)));
        if ($row === null)
            throw $this->createNotFoundException();
        $row['data'] = \json_decode($row['data'], true);

        // Create form
        $form = $this->createForm(RegistrationType::class, $row['data'], ['mapped' => false]);
        $form->handleRequest($request);

        // Handle form
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $db->update('registrations',
                ['data' => \json_encode($data)],
                \sprintf('confirmation_code = %s', $db->quote($token))
            );
            return $this->redirectToRoute('registrations.pending.list');
        }

        return $this->render('registrations/pending_form.html.twig', [
            'registration' => $row,
            'form' => $form,
        ]);
    }
}
