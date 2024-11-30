<?php

namespace App\Controller;

use App\Service\Authentication;
use App\Service\Database;
use App\Service\Policy;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class NotificationsController extends AbstractController
{
    public function __construct(
        private Authentication $auth,
        private Database $db,
        private Policy $policy,
    ){
    }

    private function get_notifications(): array
    {
        if (!$this->auth->loggedIn)
            return [];

        $notifications = [];

        if ($this->auth->getIdentity()->is_pending())
            $notifications[] = [
                'message' => __('Your membership application hasn’t been accepted yet by our secretary. Some pages (for example photo albums and sign-up forms) won’t be accessible until then. This process might take up to a few days to complete.'),
            ];

        $member = $this->auth->getIdentity()->member();
        $birthday = new \DateTime($member['geboortedatum']);
        if ($birthday->format('m-d') == (new \DateTime())->format('m-d'))
            $notifications[] = [
                'message' => sprintf(__('Happy birthday, %s! 🥳'), \member_first_name($member)),
                'class' => 'is-size-5',
            ];

        $proposed_events = array_filter(
            $this->db->getModel('DataModelAgenda')->get_proposed(),
            [$this->policy, 'userCanModerate']
        );

        if (count($proposed_events) > 0)
            $notifications[] = [
                'url' => $this->generateUrl('events.moderate'),
                'message' => __N(
                    'There is %d event waiting for your confirmation',
                    'There are %d events waiting for your confirmation',
                    count($proposed_events),
                ),
            ];

        $unreviewed_profile_pictures = array_filter(
            $this->db->getModel('DataModelProfilePicture')->find(['reviewed' => false]),
            [$this->policy, 'userCanReview']
        );

        if (count($unreviewed_profile_pictures) > 0)
            $notifications[] = [
                'url' => $this->generateUrl('profile_pictures.list'),
                'message' => __N(
                    'There is %d profile picture waiting for review',
                    'There are %d profiles picture waiting for review',
                    count($unreviewed_profile_pictures),
                ),
            ];

        return $notifications;
    }

    private function get_always_visible(): bool
    {
        return $this->policy->userCanModerate($this->db->getModel('DataModelAgenda')->new_iter(['replacement_for' => true]))
            || $this->policy->userCanReview($this->db->getModel('DataModelProfilePicture')->new_iter(['reviewed' => false]));
    }

    /**
     * Render notifications menu fragment. Only accessed internally by Twig.
     * Not sure whether it should be an embedded controller or a service. As you
     * can see, I picked controller. - Martijn Luinstra (2024-11)
     */
    public function menu(): Response
    {
        return $this->render('notifications/menu.html.twig', [
            'notifications' => $this->get_notifications(),
            'always_visible' => $this->get_always_visible(),
        ]);
    }
}
