<?php

namespace App\Legacy\Authentication;

use App\Legacy\Authentication\IdentityProviderInterface;

class GuestIdentityProvider implements IdentityProviderInterface
{
    public function is_member()
    {
        return false;
    }

    public function is_donor()
    {
        return false;
    }

    public function is_pending()
    {
        return false;
    }

    public function is_device()
    {
        return false;
    }

    public function member_in_committee($committee = null)
    {
        return false;
    }

    public function member()
    {
        return null;
    }

    public function get($key, $default_value = null)
    {
        return $default_value;
    }

    public function can_impersonate()
    {
        return false;
    }
}
