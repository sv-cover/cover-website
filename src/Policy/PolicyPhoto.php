<?php

namespace App\Policy;

use App\DataModel\DataModelPhoto;
use App\Legacy\Authentication\Authentication;
use App\Legacy\Authentication\IdentityProviderInterface;
use App\Legacy\Database\DataIter;
use App\Legacy\Policy\PolicyInterface;
use App\Policy\PolicyPhotobook;

class PolicyPhoto implements PolicyInterface
{
    protected IdentityProviderInterface $identity;

    public static function getSupportedModel(): string
    {
        return DataModelPhoto::class;
    }

    public function __construct(
        protected Authentication $auth,
        protected PolicyPhotobook $policyPhotobook,
    ) {
        $this->identity = $auth->getIdentity();
    }

    public function userCanCreate(DataIter $iter): bool
    {
        return $this->policyPhotobook->userCanUpdate($iter['scope']);
    }

    public function userCanRead(DataIter $iter): bool
    {
        return $this->policyPhotobook->userCanRead($iter['scope']);
    }

    public function userCanUpdate(DataIter $iter): bool
    {
        return $this->policyPhotobook->userCanUpdate($iter['scope']);
    }

    public function userCanDelete(DataIter $iter): bool
    {
        return $this->policyPhotobook->userCanUpdate($iter['scope']);
    }

    public function userCanDownload(DataIter $iter): bool
    {
        return $this->auth->loggedIn;
    }

    public function userCanLike(DataIter $iter): bool
    {
        return $this->auth->loggedIn;
    }

    public function userCanSetPrivacy(DataIter $iter): bool
    {
        return $this->auth->loggedIn;
    }
}
