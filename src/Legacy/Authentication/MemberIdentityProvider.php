<?php

namespace App\Legacy\Authentication;

use App\DataIter\DataIterMember;
use App\DataModel\DataModelMember;
use App\Exception\NotFoundException;
use App\Legacy\Authentication\IdentityProviderInterface;
use App\Legacy\Authentication\SessionProviderInterface;

class MemberIdentityProvider implements IdentityProviderInterface
{
    protected ?DataIterMember $member;

    public function __construct(
        protected SessionProviderInterface $session_provider,
        protected DataModelMember $member_model,
    ) {
    }

    public function is_member()
    {
        return $this->session_provider->logged_in()
            && $this->member()->is_member();
    }

    public function is_donor()
    {
        return $this->session_provider->logged_in()
            && $this->member()->is_donor();
    }

    public function is_pending()
    {
        return $this->session_provider->logged_in()
            && $this->member()->is_pending();
    }

    public function is_device()
    {
        return false;
    }

    public function member_in_committee($committee = null)
    {
        return $this->session_provider->logged_in() && ($committee !== null
            ? in_array($committee, $this->member()['committees'])
            : count($this->member()['committees']));
    }

    public function member()
    {
        if (!$this->session_provider->logged_in())
            return null;

        if ($this->member === null) {
            try {
                $this->member = $this->member_model->get_iter($this->session_provider->get_session()['member_id']);
            } catch (NotFoundException $e) {
                // We are logged in as someone who doesn't exist. Let's logout and prevent any further undefined behavior
                $this->session_provider->logout();
                $this->member = null;

                // But also rethrow the exception
                throw $e;
            }
        }

        return $this->member;
    }

    public function get($key, $default_value = null)
    {
        if (!$this->session_provider->logged_in())
            return $default_value;
        elseif (!empty($this->member()[$key]))
            return $this->member()[$key];
        else
            return $default_value;
    }

    public function can_impersonate()
    {
        return false;
    }
}
