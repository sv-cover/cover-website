<?php

namespace App\Bridge;

use App\Legacy\Authentication\Authentication;
use Sentry\UserDataBag;

class Sentry
{
    public function __construct(
        private Authentication $auth,
    ) {
    }

    public function getBeforeSend(): callable
    {
        return function(\Sentry\Event $event, ?\Sentry\EventHint $hint): ?\Sentry\Event {
            if ($this->auth->loggedIn) {
                $user = UserDataBag::createFromArray([
                    'id' => $this->auth->identity->get('id'),
                ]);
                if ($event->getUser())
                    $user->merge($event->getUser());
                $event->setUser($user);
                // Also set user context so we've got some more info
                $event->setContext('user', [
                    'id' => $this->auth->identity->get('id'),
                    'session_id' => $this->auth->auth->get_session()->get('id'),
                ]);
            }
            return $event;
        };
    }
}
