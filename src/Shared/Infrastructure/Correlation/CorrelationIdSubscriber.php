<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Shared\Infrastructure\Correlation;

use SovereignStack\Lms\Shared\Domain\Uuid\UuidGeneratorInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final readonly class CorrelationIdSubscriber implements EventSubscriberInterface
{
    public const HEADER = 'X-Correlation-ID';

    public const ATTRIBUTE = '_correlation_id';

    private const VALID_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9._:-]{0,127}$/D';

    public function __construct(
        private UuidGeneratorInterface $uuidGenerator,
        private CorrelationIdHolder $holder,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 256],
            KernelEvents::RESPONSE => ['onKernelResponse', -256],
            KernelEvents::TERMINATE => ['onKernelTerminate', -256],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $requested = $request->headers->get(self::HEADER);

        $correlationId = null !== $requested && 1 === preg_match(self::VALID_PATTERN, $requested)
            ? $requested
            : $this->uuidGenerator->generate();

        $request->attributes->set(self::ATTRIBUTE, $correlationId);
        $this->holder->set($correlationId);
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $correlationId = $event->getRequest()->attributes->get(self::ATTRIBUTE);

        if (\is_string($correlationId)) {
            $event->getResponse()->headers->set(self::HEADER, $correlationId);
        }
    }

    public function onKernelTerminate(TerminateEvent $event): void
    {
        $this->holder->clear();
    }
}
