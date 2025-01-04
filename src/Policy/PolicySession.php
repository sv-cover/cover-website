<?php

namespace App\Policy;

use App\DataModel\DataModelCommissie;
use App\DataModel\DataModelSession;
use App\Legacy\Authentication\Authentication;
use App\Legacy\Authentication\IdentityProviderInterface;
use App\Legacy\Database\DataIter;
use App\Legacy\Policy\PolicyInterface;

// NB: this policy is currently only used for device sessions.
class PolicySession implements PolicyInterface
{
    protected IdentityProviderInterface $identity;

    public static function getSupportedModel(): string
    {
        return DataModelSession::class;
    }

    public function __construct(
        protected Authentication $auth,
    ) {
        $this->identity = $auth->getIdentity();
    }

    public function userCanCreate(DataIter $session): bool
    {
        return false;
    }

    public function userCanRead(DataIter $session): bool
    {
        // You can see your own sessions
        if ($session['member_id'] == $this->identity->get('id'))
            return true;

        // WebCie can see all sessions
        return $this->identity->member_in_committee(DataModelCommissie::WEBCIE);
    }

    public function userCanUpdate(DataIter $session): bool
    {
        // Only AC/DCee can update sessions, and only device sessions.
        return $session['type'] === 'device'
            && $this->identity->member_in_committee(DataModelCommissie::WEBCIE);
    }

    public function userCanDelete(DataIter $session): bool
    {
        return $this->userCanUpdate($session);
    }
}
