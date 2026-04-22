<?php

namespace App\Exception;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/* Extension of Symfony's NotFoundHttpException for use by legacy components.
 */
class NotFoundException extends NotFoundHttpException
{
    //
}
