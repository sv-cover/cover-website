<?php

namespace App\Policy;

use App\DataModel\DataModelCommissie;
use App\DataModel\DataModelMember;
use App\Legacy\Authentication\Authentication;
use App\Legacy\Authentication\IdentityProviderInterface;
use App\Legacy\Database\DataIter;
use App\Legacy\Policy\PolicyInterface;

class PolicyMember implements PolicyInterface
{
    protected IdentityProviderInterface $identity;

    public static function getSupportedModel(): string
    {
        return DataModelMember::class;
    }

    public function __construct(
        protected Authentication $auth,
    ) {
        $this->identity = $auth->getIdentity();
    }

    public function userCanCreate(DataIter $iter): bool
    {
        // Nobody can create except for the API, which is called by Secretary.
        return false;
    }

    public function userCanRead(DataIter $iter): bool
    {
        // You can see yourself
        if ($iter['id'] == $this->identity->get('id'))
            return true;

        // You can see members, honourary members and donors
        $allowedStatuses = [
            DataModelMember::STATUS_LID,
            DataModelMember::STATUS_ERELID,
            DataModelMember::STATUS_DONATEUR,
        ];
        if (in_array($iter['type'], $allowedStatuses))
            return true;

        // And only the board can see the rest.
        return $this->identity->member_in_committee(DataModelCommissie::BOARD)
            || $this->identity->member_in_committee(DataModelCommissie::CANDY);
    }

    public function userCanUpdate(DataIter $iter): bool
    {
        if ($iter['id'] == $this->identity->get('id'))
            return true;

        return $this->identity->member_in_committee(DataModelCommissie::BOARD)
            || $this->identity->member_in_committee(DataModelCommissie::CANDY)
            || $this->identity->member_in_committee(DataModelCommissie::WEBCIE);
    }

    public function userCanDelete(DataIter $iter): bool
    {
        // Nobody can delete, because that is untested behaviour.
        return false;
    }

    public function userCanReadVcard(DataIter $iter): bool
    {
        return $this->identity->is_member();
    }
}
