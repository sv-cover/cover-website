<?php

namespace App\Exception;

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Legacy exception. Should be replaced with AccessDeniedException from symfony/security
 */
class UnauthorizedException extends AccessDeniedHttpException
{
    //
}
