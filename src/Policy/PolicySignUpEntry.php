<?php

namespace App\Policy;

use App\DataModel\DataModelCommissie;
use App\DataModel\DataModelSignUpEntry;
use App\Legacy\Authentication\Authentication;
use App\Legacy\Authentication\IdentityProviderInterface;
use App\Legacy\Database\DataIter;
use App\Legacy\Policy\PolicyInterface;

class PolicySignUpEntry implements PolicyInterface
{
    protected IdentityProviderInterface $identity;

    public static function getSupportedModel(): string
    {
        return DataModelSignUpEntry::class;
    }

    public function __construct(
        protected Authentication $auth,
    ) {
        $this->identity = $auth->getIdentity();
    }

    public function userCanCreate(DataIter $entry): bool
    {
        // Active members can sign up if it is open
        if ($entry['form']->is_open())
            return $this->identity->is_member()
                || $this->identity->is_donor();

        // The committee of the activity can always add people to the activity
        if ($this->identity->member_in_committee($entry['form']['committee_id']))
            return true;

        return false;
    }

    public function userCanRead(DataIter $entry): bool
    {
        // Board can read & update them
        if (
            $this->identity->member_in_committee(DataModelCommissie::BOARD)
            || $this->identity->member_in_committee(DataModelCommissie::CANDY)
        )
            return true;

        // and of course the committee of the form can
        if ($this->identity->member_in_committee($entry['form']['committee_id']))
            return true;

        // The member of the entry can read their own entries
        if ($this->identity->get('id') === $entry['member_id'])
            return true;

        return false;
    }

    public function userCanUpdate(DataIter $entry): bool
    {
        // Board can read & update them
        if (
            $this->identity->member_in_committee(DataModelCommissie::BOARD)
            || $this->identity->member_in_committee(DataModelCommissie::CANDY)
        )
            return true;

        // and of course the committee of the form can
        if ($this->identity->member_in_committee($entry['form']['committee_id']))
            return true;

        // The member of the entry can read their own entries
        if ($this->identity->get('id') === $entry['member_id'])
            return $entry['form']->is_open();

        return false;
    }

    public function userCanDelete(DataIter $entry): bool
    {
        // Only the board and the committee can delete entries. You cannot "just" delete your own entry.
        if ($this->identity->member_in_committee($entry['form']['committee_id']))
            return true;

        if (
            $this->identity->member_in_committee(DataModelCommissie::BOARD)
            || $this->identity->member_in_committee(DataModelCommissie::CANDY)
        )
            return true;

        return false;
    }
}
