<?php
namespace App\EventSubscriber;

use App\DataModel\DataModelApplication;
use App\Exception\UnauthorizedException;
use App\Controller\ApiController;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class ApiSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        // return the subscribed events, their methods and priorities
        return [
            KernelEvents::CONTROLLER => 'onKernelController',
            KernelEvents::EXCEPTION => 'onKernelException',
        ];
    }

    public function __construct(
        private DataModelApplication $applicationModel,
    ) {
    }

    public function onKernelController(ControllerEvent $event): void
    {
        $controller = $event->getController();
        $request = $event->getRequest();

        // when a controller class defines multiple action methods, the controller
        // is returned as [$controllerInstance, 'methodName']
        if (is_array($controller))
            $controller = $controller[0];

        if (!($controller instanceof ApiController))
            return;

        // Flag the request as API request, useful for later
        $request->attributes->set('is_api_request', true);

        if (!$request->isMethod('POST'))
            return;

        // We've got a post request to the API controller

        if (empty($request->headers->get('X-App')))
            throw new AccessDeniedHttpException('App name is missing');

        $app = $this->applicationModel->find_one(['key' => $request->headers->get('X-App')]);

        if (!$app)
            throw new AccessDeniedHttpException('No app with that name available');

        $contentHash = sha1($request->getContent() . $app['secret']);

        $headerHash = $request->headers->get('X-Hash');

        if (empty($headerHash) || $contentHash != $headerHash)
            throw new AccessDeniedHttpException('Checksum does not match');

        $request->attributes->set('app', $app);
    }

    /**
     * Customize error response on the API. This is for backward-compatibility.
     * Future API revisions should probably use Symfony's implementation.
     */
    public function onKernelException(ExceptionEvent $event): void
    {
        // Makes sure we're handling an API request. This is set in this.onKernelController
        if (!$event->getRequest()->attributes->get('is_api_request', false))
            return;

        // We're in the API controller. Start caring.
        $exception = $event->getThrowable();

        if (
            !($exception instanceof \InvalidArgumentException)
            && !($exception instanceof UnauthorizedException)
        ) {
            \Sentry\captureException($exception);
        }

        if ($exception instanceof HttpExceptionInterface) {
            $code = $exception->getStatusCode();
            $headers = $exception->getHeaders();
        } else {
            $code = 500;
            $headers = [];
        }

        $responseData = [
            'success' => false,
            'error' => $exception->getMessage(),
        ];

        $event->allowCustomResponseCode();
        $event->setResponse(new JsonResponse($responseData, 200, $headers));
    }
}
