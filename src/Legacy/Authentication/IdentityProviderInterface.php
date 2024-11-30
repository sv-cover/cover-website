<?php

namespace App\Legacy\Authentication;

interface IdentityProviderInterface
{
    public function is_member();
    public function is_donor();
    public function is_pending();
    public function is_device();
    public function member_in_committee($committee = null);
    public function can_impersonate();
    public function member();
    public function get($key, $default_value = null);
}
