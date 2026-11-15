<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Identity\Infrastructure\Security;

use SovereignStack\Lms\Identity\Domain\Repository\UserRepositoryInterface;
use SovereignStack\Lms\Identity\Domain\ValueObject\Email;
use SovereignStack\Lms\Shared\Application\Audit\AuditLoggerInterface;
use SovereignStack\Lms\Shared\Domain\Clock\ClockInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;
use Symfony\Component\Security\Http\SecurityEvents;

final readonly class AuthenticationSubscriber implements EventSubscriberInterface
{
    private const UNKNOWN_USER_ID = '00000000-0000-0000-0000-000000000000';

    public function __construct(
        private UserRepositoryInterface $users,
        private AuditLoggerInterface $audit,
        private ClockInterface $clock,
    ) {}

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            SecurityEvents::INTERACTIVE_LOGIN => 'onInteractiveLogin',
            LoginFailureEvent::class => 'onLoginFailure',
            LogoutEvent::class => 'onLogout',
        ];
    }

    public function onInteractiveLogin(InteractiveLoginEvent $event): void
    {
        $securityUser = $event->getAuthenticationToken()->getUser();

        if (!$securityUser instanceof SecurityUser) {
            return;
        }

        $user = $securityUser->getDomainUser();
        $now = $this->clock->now();

        $user->recordSuccessfulLogin($now);
        $this->users->updateLoginState($user);

        $session = $event->getRequest()->getSession();
        $session->set('_security_login_at', $now->getTimestamp());
        $session->set('_security_user_updated_at', $user->updatedAt()->format('U.u'));

        $this->audit->record('user.login_succeeded', $user->id()->value(), null, 'user', $user->id()->value());
    }

    public function onLoginFailure(LoginFailureEvent $event): void
    {
        $request = $event->getRequest();
        $rawEmail = $request->request->get('email');

        if (!\is_string($rawEmail) || '' === trim($rawEmail)) {
            return;
        }

        try {
            $email = Email::fromString($rawEmail);
        } catch (\InvalidArgumentException) {
            $this->audit->record('user.login_failed', null, null, 'user', self::UNKNOWN_USER_ID);

            return;
        }

        $user = $this->users->findByEmail($email);

        if (null === $user) {
            $this->audit->record('user.login_failed', null, null, 'user', self::UNKNOWN_USER_ID);

            return;
        }

        $user->recordFailedLogin($this->clock->now());
        $this->users->updateLoginState($user);

        $this->audit->record('user.login_failed', $user->id()->value(), null, 'user', $user->id()->value());
    }

    public function onLogout(LogoutEvent $event): void
    {
        $securityUser = $event->getToken()?->getUser();

        if (!$securityUser instanceof SecurityUser) {
            return;
        }

        $user = $securityUser->getDomainUser();

        $this->audit->record('user.logged_out', $user->id()->value(), null, 'user', $user->id()->value());
    }
}
