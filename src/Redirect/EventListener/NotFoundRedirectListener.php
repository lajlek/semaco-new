<?php

declare(strict_types=1);

namespace App\Redirect\EventListener;

use App\Redirect\Service\RedirectResolver;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

#[AsEventListener(event: 'kernel.exception', priority: 100)]
final readonly class NotFoundRedirectListener
{
    public function __construct(private RedirectResolver $redirectResolver)
    {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        if (!$event->isMainRequest() || !$event->getThrowable() instanceof NotFoundHttpException) {
            return;
        }

        $response = $this->redirectResolver->resolve($event->getRequest());
        if ($response !== null) {
            $event->setResponse($response);
        }
    }
}
