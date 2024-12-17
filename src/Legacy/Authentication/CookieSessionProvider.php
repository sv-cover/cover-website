<?php

namespace App\Legacy\Authentication;

use App\DataIter\DataIterSession;
use App\DataModel\DataModelMember;
use App\DataModel\DataModelSession;
use App\Legacy\Authentication\SessionProviderInterface;

class CookieSessionProvider implements SessionProviderInterface
{
    private ?DataIterSession $session;

    private bool $is_restored = false;

    public function __construct(
        protected DataModelSession $session_model,
        protected DataModelMember $member_model,
    ) {
    }

    protected function get_session_id()
    {
        if (!empty($_GET['session_id']))
            return $_GET['session_id'];

        if (!empty($_COOKIE['cover_session_id']))
            return $_COOKIE['cover_session_id'];

        return null;
    }

    protected function resume_session()
    {
        $this->session = $this->session_model->resume($this->get_session_id());
        $this->is_restored = true;
    }

    public function login($email, $password, $remember, $application)
    {
        $member = $this->member_model->login($email, $password);

        if (!$member)
            return false;

        $session_timeout = $remember ? '7 DAY' : '1 HOUR';

        $this->session = $this->session_model->create(
            $member->get_id(),
            $application,
            $session_timeout);

        // Set the cookie. Doesn't really matter it is set for such a long time,
        // inactive sessions will be removed from the database and rendered
        // invalid automatically. A low value will cause people to be logged out.
        $cookie_time = time() + 24 * 3600 * 31 * 12;
        // TODO: set cookie_time to 0 if $remember == true, then session will end when browser closes
 
        set_domain_cookie('cover_session_id',
            $this->session->get('session_id'),
            $cookie_time);

        return true;
    }

    public function logout()
    {
        $session = $this->get_session();

        if (!$session)
            return true;

        $this->session_model->delete($session);

        set_domain_cookie('cover_session_id', null);

        $this->is_restored = false;
        $this->session = null;

        return true;
    }

    public function create_device_session($application)
    {
        if ($this->logged_in())
            return false;

        $this->session = $this->session_model->create(
            null,
            $application,
            '99 YEAR',
            'device'
        );

        // Set the cookie.
        $cookie_time = time() + 24 * 3600 * 365 * 99;

        set_domain_cookie('cover_session_id',
            $this->session->get('session_id'),
            $cookie_time);

        return true;
    }

    public function logged_in()
    {
        $session = $this->get_session();
        // device sessions will appear as not logged in, see DeviceIdentityProvider
        return !empty($session) && $session['type'] === 'member';
    }

    public function get_session()
    {
        if (!$this->is_restored)
            $this->resume_session();
        return $this->session ?? null;
    }
}
