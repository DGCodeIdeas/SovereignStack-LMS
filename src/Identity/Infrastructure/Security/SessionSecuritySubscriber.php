<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Identity\Infrastructure\Security;

use SovereignStack\Lms\Identity\Domain\Enum\UserStatus;
use SovereignStack\Lms\Shared\Application\Audit\AuditLoggerInterface;
use SovereignStack\Lms\Shared\Domain\Clock\ClockInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final readonly class SessionSecuritySubscriber implements EventSubscriberInterface
{
    private const MAX_SESSION_AGE_SECONDS = 28800;

    private const SKIP_PREFIXES = [
        '/login',
        '/register',
        '/reset-password',
        '/verify/email',
        '/health',
        '/ready',
        '/assets',
    ];

    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private AuditLoggerInterface $audit,
        private ClockInterface $clock,
    ) {}

    /**
     * @return array<string, array{string, int}>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', -100],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if ($this->isSkipped($request->getPathInfo()) || !$request->hasSession()) {
            return;
        }

        $securityUser = $this->tokenStorage->getToken()?->getUser();

        if (!$securityUser instanceof SecurityUser) {
            return;
        }

        $session = $request->getSession();
        $reason = $this->revocationReason($session, $securityUser);

        if (null === $reason) {
            return;
        }

        $user = $securityUser->getDomainUser();

        $this->tokenStorage->setToken(null);
        $session->invalidate();

        $this->audit->record(
            'user.session_revoked',
            $user->id()->value(),
            null,
            'user',
            $user->id()->value(),
            ['reason' => $reason],
        );

        $event->setResponse(new RedirectResponse('/login'));
    }

    private function isSkipped(string $path): bool
    {
        foreach (self::SKIP_PREFIXES as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return true;
            }
        }

        return false;
    }

    private function revocationReason(SessionInterface $session, SecurityUser $securityUser): ?string
    {
        $user = $securityUser->getDomainUser();

        if (UserStatus::ACTIVE !== $user->status()) {
            return 'status_inactive';
        }

        $sessionUpdatedAt = $session->get('_security_user_updated_at');

        if (!\is_string($sessionUpdatedAt) || $sessionUpdatedAt !== $user->updatedAt()->format('U.u')) {
            return 'user_changed';
        }

        $loginAt = $session->get('_security_login_at');

        if (!\is_int($loginAt)) {
            return 'session_expired';
        }

        if ($this->clock->now()->getTimestamp() - $loginAt > self::MAX_SESSION_AGE_SECONDS) {
            return 'session_expired';
        }

        return null;
    }
}
