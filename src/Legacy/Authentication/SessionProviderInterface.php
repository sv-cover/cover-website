<?php

namespace App\Legacy\Authentication;

interface SessionProviderInterface
{
    public function logged_in();

    public function get_session();
}
