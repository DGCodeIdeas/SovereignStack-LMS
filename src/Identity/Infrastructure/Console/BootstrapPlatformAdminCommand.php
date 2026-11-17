<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Identity\Infrastructure\Console;

use SovereignStack\Lms\Identity\Application\Contract\PasswordHasherInterface;
use SovereignStack\Lms\Identity\Application\Policy\PasswordPolicy;
use SovereignStack\Lms\Identity\Domain\Entity\User;
use SovereignStack\Lms\Identity\Domain\Entity\UserProfile;
use SovereignStack\Lms\Identity\Domain\Exception\EmailAlreadyRegisteredException;
use SovereignStack\Lms\Identity\Domain\Repository\RoleRepositoryInterface;
use SovereignStack\Lms\Identity\Domain\Repository\UserProfileRepositoryInterface;
use SovereignStack\Lms\Identity\Domain\Repository\UserRepositoryInterface;
use SovereignStack\Lms\Identity\Domain\ValueObject\Email;
use SovereignStack\Lms\Identity\Domain\ValueObject\RoleIdentifier;
use SovereignStack\Lms\Identity\Domain\ValueObject\UserId;
use SovereignStack\Lms\Shared\Application\Audit\AuditLoggerInterface;
use SovereignStack\Lms\Shared\Application\Exception\ConflictException;
use SovereignStack\Lms\Shared\Application\Exception\ValidationException;
use SovereignStack\Lms\Shared\Application\Transaction\TransactionManagerInterface;
use SovereignStack\Lms\Shared\Domain\Clock\ClockInterface;
use SovereignStack\Lms\Shared\Domain\Uuid\UuidGeneratorInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:bootstrap-platform-admin',
    description: 'Create the first platform administrator account interactively.',
)]
final class BootstrapPlatformAdminCommand extends Command
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly UserProfileRepositoryInterface $profiles,
        private readonly RoleRepositoryInterface $roles,
        private readonly PasswordHasherInterface $hasher,
        private readonly AuditLoggerInterface $audit,
        private readonly TransactionManagerInterface $transactions,
        private readonly UuidGeneratorInterface $uuid,
        private readonly ClockInterface $clock,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($this->platformAdminExists()) {
            $io->error('Bootstrap aborted: a platform administrator already exists.');

            return Command::FAILURE;
        }

        $emailInput = $io->ask('Email address');

        if (!\is_string($emailInput)) {
            $io->error('Bootstrap aborted: an email address is required.');

            return Command::FAILURE;
        }

        try {
            $email = Email::fromString($emailInput);
        } catch (\InvalidArgumentException $exception) {
            $io->error('Bootstrap aborted: '.$exception->getMessage());

            return Command::FAILURE;
        }

        $displayNameInput = $io->ask('Display name', '');
        $displayName = \is_string($displayNameInput) ? trim($displayNameInput) : '';

        if ('' === $displayName) {
            $io->error('Bootstrap aborted: a display name is required.');

            return Command::FAILURE;
        }

        $password = $io->askHidden('Password');
        $confirmation = $io->askHidden('Confirm password');

        if (!\is_string($password) || !\is_string($confirmation)) {
            $io->error('Bootstrap aborted: a password and confirmation are required.');

            return Command::FAILURE;
        }

        if (!hash_equals($password, $confirmation)) {
            $io->error('Bootstrap aborted: the password confirmation does not match.');

            return Command::FAILURE;
        }

        try {
            PasswordPolicy::assertValid($password, $email->value());
        } catch (ValidationException $exception) {
            $io->error('Bootstrap aborted: '.$exception->getMessage());

            return Command::FAILURE;
        }

        try {
            $userId = $this->transactions->transactional(
                function () use ($email, $displayName, $password): UserId {
                    if ($this->platformAdminExists()) {
                        throw new ConflictException('Bootstrap aborted: a platform administrator already exists.');
                    }

                    if ($this->users->emailExists($email)) {
                        throw EmailAlreadyRegisteredException::forEmail($email->value());
                    }

                    $now = $this->clock->now();
                    $userId = UserId::fromString($this->uuid->generate());

                    $user = User::register($userId, $email, $this->hasher->hash($password), $now);
                    $user->verifyEmail($now);
                    $this->users->save($user);

                    $profile = UserProfile::create(
                        $this->uuid->generate(),
                        $userId,
                        $displayName,
                        'UTC',
                        [],
                        $now,
                    );
                    $this->profiles->save($profile);

                    $role = RoleIdentifier::fromString(RoleIdentifier::PLATFORM_ADMIN);
                    $this->roles->assign($userId, $role, null, null, $now);

                    $this->audit->record(
                        'platform.bootstrap_completed',
                        null,
                        null,
                        'user',
                        $userId->value(),
                        ['role' => $role->value()],
                    );

                    return $userId;
                },
            );
        } catch (ConflictException|EmailAlreadyRegisteredException $exception) {
            $io->error('Bootstrap aborted: '.$exception->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf(
            'Platform administrator "%s" (%s) created successfully.',
            $email->value(),
            $userId->value(),
        ));

        return Command::SUCCESS;
    }

    private function platformAdminExists(): bool
    {
        return $this->roles->countUsersWithRole(RoleIdentifier::fromString(RoleIdentifier::PLATFORM_ADMIN)) > 0;
    }
}
