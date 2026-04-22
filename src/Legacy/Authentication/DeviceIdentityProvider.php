<?php

namespace App\Legacy\Authentication;

use App\Legacy\Authentication\GuestIdentityProvider;
use App\Legacy\Authentication\SessionProviderInterface;

/**
 * DeviceIdentityProvider: An identity provider for devices that are supposed to
 * have similar access to certain resources as members. For example, the
 * promotion/digital signage screen in the Cover room should display photo books
 * and the calendar as it appears to members.
 * 
 * Device sessions have to be enabled before they become useful: every device
 * can create a device session by navigating to the correct url, but only admins
 * can enable device sessions. Disabled device sessions will appear as guest
 * sessions / anonymous users / not-logged-in sessions.
 * 
 * Note that device sessions will appear as not logged-in, so 
 * get_auth()-logged_in() will return false, even if a device session is active.
 * This is not ideal, but done for ease of implementation: the notion that a
 * get_identity()->member() will return a member if get_auth()->logged_in()
 * returns true, is ingrained in the codebase and would cause a lot of problems
 * if device sessions would appear as logged in.
 * 
 * Device sessions can be checked for using get_identity()->is_device(), which
 * returns true only if the current session is an active device session.
 * 
 * Hope this makes sense! - Martijn Luinstra, May 2022
 */
class DeviceIdentityProvider extends GuestIdentityProvider
{
    protected $session_provider;

    public function __construct(SessionProviderInterface $session_provider)
    {
        $this->session_provider = $session_provider;
    }

    public function is_device()
    {
        $session = $this->session_provider->get_session();
        return $session['device_enabled'];
    }
}
