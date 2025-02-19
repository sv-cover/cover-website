<?php

namespace App\Legacy\Authentication;

use App\DataIter\DataIterSession;
use App\Legacy\Authentication\SessionProviderInterface;

class ConstantSessionProvider implements SessionProviderInterface
{
    /**
     * @var DataIterSession
     */
    private $session;

    public function __construc(DataIterSession $session = null)
    {
        $this->session = $session;
    }

    public function logged_in()
    {
        return $this->session !== null;
    }

    public function get_session()
    {
        return $this->session;
    }
}
