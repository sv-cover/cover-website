<?php

namespace App\Policy;

use App\DataIter\DataIterAgenda;
use App\DataModel\DataModelCommissie;
use App\DataModel\DataModelSignUpForm;
use App\Legacy\Authentication\Authentication;
use App\Legacy\Authentication\IdentityProviderInterface;
use App\Legacy\Database\DataIter;
use App\Legacy\Policy\PolicyInterface;

class PolicySignUpForm implements PolicyInterface
{
    protected IdentityProviderInterface $identity;

    public static function getSupportedModel(): string
    {
        return DataModelSignUpForm::class;
    }

    public function __construct(
        protected Authentication $auth,
    ) {
        $this->identity = $auth->getIdentity();
    }

    public function userCanCreate(DataIter $form): bool
    {
        if ($form['committee_id'] !== null)
            return $this->identity->member_in_committee($form['committee_id']);
        else
            return $this->identity->member_in_committee();
    }

    public function userCanCreateForEvent(DataIterAgenda $event): bool
    {
        return $this->identity->member_in_committee($event['committee_id']);
    }

    public function userCanSignUp(DataIter $form): bool
    {
        return $this->identity->is_member() || $this->identity->is_donor();
    }

    public function userCanRead(DataIter $form): bool
    {
        if (
            $this->identity->member_in_committee(DataModelCommissie::BOARD)
            || $this->identity->member_in_committee(DataModelCommissie::CANDY)
        )
            return true;

        return $this->identity->member_in_committee($form['committee_id']);
    }

    public function userCanUpdate(DataIter $form): bool
    {
        return $this->identity->member_in_committee($form['committee_id']);
    }

    public function userCanDelete(DataIter $form): bool
    {
        return $this->identity->member_in_committee($form['committee_id']);
    }
}
