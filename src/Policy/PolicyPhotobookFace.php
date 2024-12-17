<?php
namespace App\Policy;

use App\DataModel\DataModelPhotobookFace;
use App\Legacy\Authentication\IdentityProviderInterface;
use App\Legacy\Database\DataIter;
use App\Legacy\Policy\PolicyInterface;
use App\Service\Authentication;

class PolicyPhotobookFace implements PolicyInterface
{
    protected IdentityProviderInterface $identity;

    public static function getSupportedModel(): string
    {
        return DataModelPhotobookFace::class;
    }

    public function __construct(
        protected Authentication $auth,
    ) {
        $this->identity = $auth->getIdentity();
    }

    public function userCanCreate(DataIter $iter): bool
    {
        return $this->auth->loggedIn;
    }

    public function userCanRead(DataIter $iter): bool
    {
        return $this->auth->loggedIn;
    }

    public function userCanUpdate(DataIter $iter): bool
    {
        return $this->auth->loggedIn;
    }

    public function userCanDelete(DataIter $iter): bool
    {
        return $this->auth->loggedIn;
    }
}
