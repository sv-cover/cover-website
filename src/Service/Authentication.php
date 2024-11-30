<?php

namespace App\Service;

use App\Legacy\Authentication\CookieSessionProvider;
use App\Legacy\Authentication\DeviceIdentityProvider;
use App\Legacy\Authentication\GuestIdentityProvider;
use App\Legacy\Authentication\ImpersonatingIdentityProvider;
use App\Legacy\Authentication\MemberIdentityProvider;
use App\Legacy\Authentication\SessionProviderInterface;

class Authentication
{
    private $authenticator;
    private $identity;
    private $session;

    public function getAuth()
    {
        if ($this->authenticator === null && CookieSessionProvider::class)
            $this->authenticator = new CookieSessionProvider();
        return $this->authenticator;
    }

    public function getIdentity()
    {
        $authenticator = $this->getAuth();
        if ($this->identity === null || $authenticator->get_session() !== $this->session)
        {
            $this->identity = $this->getIdentityProvider($authenticator);
            $this->session = $authenticator->get_session();
        }

        return $this->identity;
    }

    public function getIdentityProvider(SessionProviderInterface $authenticator)
    {
        if (empty($authenticator->get_session()))
            $identity = new GuestIdentityProvider();
        elseif ($authenticator->get_session()->get('type') === 'device')
            $identity = new DeviceIdentityProvider($authenticator);
        else
            $identity = new MemberIdentityProvider($authenticator);

        if ($identity->member_in_committee(COMMISSIE_EASY))
            $identity = new ImpersonatingIdentityProvider($authenticator);

        return $identity;
    }

    public function __get($name)
    {
        if ($name === 'auth')
            return $this->getAuth();
        elseif ($name === 'identity')
            return $this->getIdentity();
        elseif ($name === 'loggedIn' || $name === 'logged_in')
            return $this->getAuth()->logged_in();
    }

    public function __isset($name): bool
    {
        $props = ['auth', 'identity', 'loggedIn', 'logged_in'];
        return in_array($name, $props);
    }
}
