<?php

namespace App\Controller;

use App\Bridge\Secretary;
use App\DataIter\DataIterMember;
use App\DataModel\DataModelAgenda;
use App\DataModel\DataModelCommissie;
use App\DataModel\DataModelMailinglist;
use App\DataModel\DataModelMailinglistSubscription;
use App\DataModel\DataModelMember;
use App\DataModel\DataModelPasswordResetToken;
use App\DataModel\DataModelSession;
use App\Exception\NotFoundException;
use App\Exception\UnauthorizedException;
use App\Legacy\Authentication\Authentication;
use App\Legacy\Authentication\ConstantSessionProvider;
use App\Legacy\Authentication\DeviceIdentityProvider;
use App\Legacy\Policy\Policy;
use App\Utils\MailingListUtils;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Controller for the API. This is very much legacy code and in desperate need
 * of replacement.
 *
 * Authentication and Error handling are managed by the
 * App\EventSubscriber\ApiSubscriber subscriber. This was not only done because
 * it's neat but also to show how to do it properly in future revisions.
 */
class ApiController extends AbstractController
{
    private Request $request;

    public function __construct(
        private DataModelAgenda $eventModel,
        private DataModelCommissie $committeeModel,
        private DataModelMailinglist $mailingListModel,
        private DataModelMailinglistSubscription $mailingListSubscriptionModel,
        private DataModelMember $memberModel,
        private DataModelPasswordResetToken $passwordResetModel,
        private DataModelSession $sessionModel,
        private Authentication $auth,
        private MailerInterface $mailer,
        private Policy $policy,
        private MailingListUtils $mailingListUtils,
        private UriSigner $uriSigner,
    ) {
    }

    #[Route('/api.php', methods: ['GET', 'POST'])]
    #[Route('/api', name: 'api', methods: ['GET', 'POST'])]
    public function api(
        Request $request,
        #[MapQueryParameter] string $method,
    ): Response
    {
        $this->request = $request;

        switch ($method) {
            // GET api.php?method=agenda[&committee[]={committee}]
            case 'agenda':
                return $this->agenda();

            case 'get_agendapunt':
                return $this->getAgendapunt();

            // GET api.php?method=get_member&member_id=709<&session_id=$session_id>
            case 'get_member':
                return $this->getMember();

            // GET api.php?method=get_committees&member_id=709
            case 'get_committees':
                return $this->getCommittees();

            // POST api.php?method=session_create
            case 'session_create':
                return $this->sessionCreate();

            // POST api.php?method=session_destroy
            case 'session_destroy':
                return $this->sessionDestroy();

            // GET api.php?method=session_get_member&session_id={session}
            case 'session_get_member':
                return $this->sessionGetMember();

            // GET api.php?method=session_test_committee&session_id={session}&committee=webcie
            case 'session_test_committee':
                return $this->sessionTestCommittee();
        }

        $app = $request->attributes->get('app');

        // TODO: this is really ugly. Maybe API needs a good old rewrite
        if ($app && $app['is_admin']) {
            switch ($method) {
                // POST api.php?method=send_mailinglist_mail
                // Post body is the raw email
                case 'send_mailinglist_mail':
                    return $this->sendMailinglistMail();

                // POST api.php?method=secretary_read_member
                // Submit POST member_id=709
                case 'secretary_read_member':
                    return $this->secretaryReadMember();

                // POST api.php?method=secretary_create_member
                case 'secretary_create_member':
                    return $this->secretaryCreateMember();

                // POST api.php?method=secretary_update_member&member_id=709
                // Note that $_POST['id'] must also match $_GET['member_id']
                case 'secretary_update_member':
                    return $this->secretaryUpdateMember();

                // POST api.php?method=secretary_delete_member&member_id=709
                // Note that $_POST['id'] must also match $_GET['member_id']
                case 'secretary_delete_member':
                    return $this->secretaryDeleteMember();

                // POST api.php?method=secretary_send_welcome_mail&member_id=709
                case 'secretary_send_welcome_mail':
                    return $this->secretarySendWelcomeMail();

                // POST api.php?method=secretary_subscribe_member_to_mailinglist
                // Do post member_id and mailinglist, which may be an email address or an id
                case 'secretary_subscribe_member_to_mailinglist':
                    return $this->secretarySubscribeMemberToMailingList();
            }
        }

        throw new \InvalidArgumentException("Unknown method \"$method\".");
    }

    /***************************************************************************
     * Events
     **************************************************************************/

    private function agenda(): Response
    {
        try {
            $committees = $this->request->query->all('committee');
        } catch (BadRequestException $e) {
            $committees = $this->request->query->get('committee');
            if ($committees !== null)
                $committees = [$committees];
        }

        $activities = [];

        // TODO logged_in() incidentally works because the session is read from $_GET[session_id] by
        // the session provider. But the current session should be set more explicit.
        foreach ($this->eventModel->get_agendapunten() as $activity){
            if (!empty($committees) && !in_array($activity['committee']['login'], $committees))
                continue;
            if ($this->policy->userCanRead($activity))
                $activities[] = $activity->data;
        }

        // Add the properties that Newsletter expects
        foreach ($activities as &$activity) {
            $van = strtotime($activity['van']);
            $activity['vandatum'] = date('d', $van);
            $activity['vanmaand'] = date('m', $van);
        }

        return $this->json($activities);
    }

    private function getAgendapunt(): Response
    {
        $id = $this->request->query->getInt('id');

        if ($id === 0)
            throw new \InvalidArgumentException('Missing id parameter');

        $event = $this->eventModel->get_iter($id);

        // TODO this incidentally works because the session is read from $_GET[session_id] by
        // the session provider. But the current session should be set more explicit.
        if (!$this->policy->userCanRead($event))
            throw new UnauthorizedException('You are not authorized to read this event');

        $data = $event->data;

        // Backwards compatibility for consumers of the API
        $data['commissie'] = $data['committee_id'];

        return $this->json(['result' => $data]);
    }

    /***************************************************************************
     * Information about any member (adjusted for privacy)
     **************************************************************************/

    private function getMember(): Response
    {
        $memberId = $this->request->query->get('member_id');
        $member = $this->memberModel->get_iter($memberId);

        $data = $member->data;
        // Hide all private fields for this user. is_private() uses
        // logged_in() which uses the session_id get variable. So sessions
        // are taken into account ;)
        foreach ($data as $field => $value)
            if ($this->memberModel->is_private($member, $field, true))
                $data[$field] = null;

        // This one is passed as parameter anyway, it is already known.
        $data['id'] = (int) $memberId;

        return $this->json(['result' => \array_merge(
            $data,
            ['type' => $member['type']]
        )]);
    }

    private function getCommittees(): Response
    {
        $memberId = $this->request->query->get('member_id');
        $memberCommittees = $this->memberModel->get_commissies($memberId);

        $committees = [];

        foreach ($memberCommittees as $committeeId)
        {
            $committee = $this->committeeModel->get_iter($committeeId);
            $committees[$committee['login']] = $committee['naam'];
        }

        return $this->json(['result' => $committees]);
    }

    /***************************************************************************
     * Session
     **************************************************************************/

    private function sessionCreate(): Response
    {
        trigger_error('The session_create method is deprecated and will be removed in the future.', E_USER_DEPRECATED);

        $email = $this->request->getPayload()->get('email');
        $password = $this->request->getPayload()->get('password');
        $application = $this->request->getPayload()->get('application', 'api');

        if (!($member = $this->memberModel->login($email, $password)))
            throw new \InvalidArgumentException('Invalid username or password');

        $session = $this->sessionModel->create($member->get_id(), $application);

        return $this->json(['result' => [
            'session_id' => $session->get('session_id'),
            'details' => $member->data
        ]]);
    }

    private function sessionDestroy(): Response
    {
        trigger_error('The session_destroy method is deprecated and will be removed in the future.', E_USER_DEPRECATED);

        $sessionId = $this->request->getPayload()->get('session_id');

        $session = $this->sessionModel->resume($sessionId);
        $this->sessionModel->delete($session);

        return $this->json([]);
    }

    private function sessionGetMember(): Response
    {
        $sessionId = $this->request->query->get('session_id');

        $session = $this->sessionModel->resume($sessionId);

        if (!$session)
            throw new \InvalidArgumentException('Invalid session id');

        $auth = new ConstantSessionProvider($session);

        $ident = $this->auth->getIdentityProvider($auth);

        // Can't do anything with a device session
        if (is_a($ident, DeviceIdentityProvider::class))
            return [];

        $fields = \array_merge(DataIterMember::fields(), ['type']);

        // Prepare data for member
        $member = $ident->member();
        $data = [];
        foreach ($fields as $field)
            $data[$field] = $member[$field];

        // Prepare committee data
        $committeeData = [];

        // $committee_ids = $ident->get_override_committees() ?? $member['committees'];
        $committees = $this->committeeModel->find(['id__in' => $member->get_committees()]);

        // For now just return login and committee name
        foreach ($committees as $committee)
            $committeeData[$committee['login']] = $committee['naam'];

        return $this->json([
            'result' => \array_merge($data, ['committees' => $committeeData])
        ]);
    }

    private function sessionTestCommittee(): Response
    {
        $sessionId = $this->request->query->get('session_id');

        try {
            $committees = $this->request->query->all('committee');
        } catch (BadRequestException $e) {
            $committees = $this->request->query->get('committee');
            if ($committees !== null)
                $committees = [$committees];
        }

        $session = $this->sessionModel->get_iter($sessionId);
        $auth = new ConstantSessionProvider($session);
        $ident = $this->auth->getIdentityProvider($auth);

        foreach ($committees as $committee_name) {
            // Find the committee id
            $committee = $this->committeeModel->get_from_name($committee_name);

            // And finally, test whether the searched for committee and the member is committees intersect
            if ($ident->member_in_committee($committee->get_id()))
                return $this->json([
                    'result' => true,
                    'committee' => $committee['naam']
                ]);
        }

        return $this->json(['result' => false]);
    }

    /***************************************************************************
     * Mailserver support
     **************************************************************************/

    public function sendMailinglistMail()
    {
        $bufferStream = \fopen('php://temp', 'r+');
        \stream_copy_to_stream($this->request->getContent(true), $bufferStream);

        // send_mailinglist_mail is not quiet. Let it shoud into the void.
        \ob_start();
        $returnValue = $this->mailingListUtils->sendMailingListMail($bufferStream);
        \ob_end_clean();

        if ($returnValue !== 0)
            return $this->json([
                'success' => false,
                'code' => $returnValue,
                'message' => MailingListUtils::getErrorMessage($returnValue),
            ]);

        return $this->json(['success' => true]);
    }

    /***************************************************************************
     * Stuff secretary needs
     **************************************************************************/

    private function secretaryCreateMember(): Response
    {
        $payload = $this->request->getPayload();

        $id = $payload->get('id');

        if (empty($id))
            throw new \InvalidArgumentException('Missing id field in POST');

        $data = [
            'id' => $id
        ];

        try {
            $existing = $this->memberModel->get_iter($data['id']);
            if ($existing)
                throw new \InvalidArgumentException(sprintf('Member with ID %s already exists', $data['id']));
        } catch (NotFoundException $e) {
            // all good
        }

        foreach (Secretary::FIELDS_MAP as $prop => $field)
            if ($payload->has($field))
                $data[$prop] = $payload->get($field);

        $member = new DataIterMember($this->memberModel, $data['id'], $data);
        $member['privacy'] = DataModelMember::PRIVACY_DEFAULT;

        // Create profile for this member
        $nick = $member['voornaam'];
        if (strlen($nick) > 50)
            $nick = '';
        $member['nick'] = $nick;

        $this->memberModel->insert($member);

        if (strtolower($this->request->query->get('send_email', 'true')) === 'true')
            // Optionally send welcome mail to new members. This can be disabled by clients,
            // for example when it's recreating an account for an existing/former member in a synchronisation task.
            $this->secretarySendWelcomeMail($member);

        return $this->json([
            'success' => true,
            'url' => $this->generateUrl('profile.member', ['id' => $member->get_id()], UrlGeneratorInterface::ABSOLUTE_URL),
        ]);
    }

    private function secretaryReadMember(): Response
    {
        $memberId = $this->request->getPayload()->get('member_id');
        $member = $this->memberModel->get_iter($memberId);

        $data = [];
        foreach (Secretary::FIELDS_MAP as $prop => $field)
            $data[$field] = $member[$prop];

        return $this->json(['success' => true, 'data' => $data]);
    }

    private function secretaryUpdateMember(): Response
    {
        $memberId = $this->request->query->get('member_id');
        $payload = $this->request->getPayload();

        if ($memberId != $payload->get('id'))
            throw new \InvalidArgumentException('Person ids in GET and POST do not match up');

        $member = $this->memberModel->get_iter($memberId);

        $fields_map = \array_flip(Secretary::FIELDS_MAP);

        foreach ($payload->all() as $key => $value) {
            if (!isset($fields_map[$key]) || $key === 'id')
                continue;

            $member[$fields_map[$key]] = $value;
        }

        $this->memberModel->update($member);

        return $this->json([
            'success' => true,
            'url' => $this->generateUrl('profile.member', ['id' => $member->get_id()], UrlGeneratorInterface::ABSOLUTE_URL),
        ]);
    }

    private function secretaryDeleteMember(): Response
    {
        $memberId = $this->request->query->get('member_id');
        $payload = $this->request->getPayload();

        if ($memberId != $payload->get('id'))
            throw new \InvalidArgumentException('Person ids in GET and POST do not match up');

        $member = $this->memberModel->get_iter($memberId);

        return $this->json(['success' => $this->memberModel->delete($member)]);
    }

    private function secretarySendWelcomeMail(?DataIterMember $member = null): Response
    {
        if (!$this->request->isMethod('POST'))
            // Do nothing if not post
            return $this->json();

        if (empty($member)) {
            $memberId = $this->request->query->get('member_id');
            $member = $this->memberModel->get_iter($memberId);
        }

        // Create a password
        $token = $this->passwordResetModel->create_token_for_member($member);

        // Setup e-mail
        $data = $member->data;

        $url = $this->generateUrl('password.reset', ['token' => $token['key']], UrlGeneratorInterface::ABSOLUTE_URL);
        $signed_url = $this->uriSigner->sign($url, new \DateInterval('PT24H')); // Valid for 24 hours
        $data['password_link'] = $signed_url;

        // Send email
        $email = (new TemplatedEmail())
            ->to($member['email'])
            ->replyTo(new Address('secretary@svcover.nl', 'Cover Secretary'))
            ->subject("[Cover] Welcome to Cover!")
            ->htmlTemplate('emails/join_welcome.html.twig')
            ->textTemplate('emails/join_welcome.txt.twig')
            ->context([
                'member' => $member,
                'password_link' => $signed_url,
            ])
        ;
        $this->mailer->send($email);

        $email->subject('Welcome to Cover! (' . $member->get_full_name(ignorePrivacy: true) . ')');
        $email->to('administratie@svcover.nl');
        $this->mailer->send($email);

        return $this->json(['success' => true]);
    }

    private function secretarySubscribeMemberToMailingList(): Response
    {
        $memberId = $this->request->getPayload()->get('member_id');
        $member = $this->memberModel->get_iter($memberId);

        $mailinglistId = $this->request->getPayload()->get('mailinglist');
        $mailinglist = $this->mailingListModel->get_iter_by_address($mailinglistId);

        $this->mailingListSubscriptionModel->subscribe_member($mailinglist, $member);

        return $this->json(['success' => true]);
    }
}
