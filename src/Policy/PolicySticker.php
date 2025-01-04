<?php

namespace App\Policy;

use App\DataModel\DataModelCommissie;
use App\DataModel\DataModelSticker;
use App\Legacy\Authentication\IdentityProviderInterface;
use App\Legacy\Database\DataIter;
use App\Legacy\Policy\PolicyInterface;
use App\Service\Authentication;

class PolicySticker implements PolicyInterface
{
    protected IdentityProviderInterface $identity;

    public static function getSupportedModel(): string
    {
        return DataModelSticker::class;
    }

    public function __construct(
        protected Authentication $auth,
    ) {
        $this->identity = $auth->getIdentity();
    }

    public function userCanCreate(DataIter $sticker): bool
    {
        // Members are allowed to add new stickers (also contributors etc.)
        return $this->auth->loggedIn;
    }

    public function userCanRead(DataIter $sticker): bool
    {
        return true;
    }

    public function userCanUpdate(DataIter $sticker): bool
    {
        // Board can admin the stickers
        if ($this->identity->member_in_committee(DataModelCommissie::BOARD))
            return true;

        // Only the owner can update their stickers
        if ($sticker->get('toegevoegd_door') != null)
            return $sticker->get('toegevoegd_door') == $this->identity->get('id');

        return false;
    }

    public function userCanDelete(DataIter $sticker): bool
    {
        return $this->userCanUpdate($sticker);
    }
}
