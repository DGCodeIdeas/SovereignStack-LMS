<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Identity\Presentation\Controller;

use SovereignStack\Lms\Identity\Application\Command\AssignRoleCommand;
use SovereignStack\Lms\Identity\Application\Command\DeactivateUserCommand;
use SovereignStack\Lms\Identity\Application\Command\RevokeRoleCommand;
use SovereignStack\Lms\Identity\Application\Contract\UserOrganisationsInterface;
use SovereignStack\Lms\Identity\Application\Handler\AssignRoleHandler;
use SovereignStack\Lms\Identity\Application\Handler\DeactivateUserHandler;
use SovereignStack\Lms\Identity\Application\Handler\RevokeRoleHandler;
use SovereignStack\Lms\Identity\Domain\Exception\UserNotFoundException;
use SovereignStack\Lms\Identity\Domain\Repository\RoleRepositoryInterface;
use SovereignStack\Lms\Identity\Domain\Repository\UserProfileRepositoryInterface;
use SovereignStack\Lms\Identity\Domain\Repository\UserRepositoryInterface;
use SovereignStack\Lms\Identity\Domain\ValueObject\UserId;
use SovereignStack\Lms\Identity\Infrastructure\Security\SecurityUser;
use SovereignStack\Lms\Shared\Application\Exception\ConflictException;
use SovereignStack\Lms\Shared\Application\Exception\ForbiddenException;
use SovereignStack\Lms\Shared\Application\Exception\ValidationException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/users')]
final class AdminUserController extends AbstractController
{
    private const PAGE_SIZE = 50;

    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly UserProfileRepositoryInterface $profiles,
        private readonly RoleRepositoryInterface $roles,
        private readonly AssignRoleHandler $assignRole,
        private readonly RevokeRoleHandler $revokeRole,
        private readonly DeactivateUserHandler $deactivate,
        private readonly UserOrganisationsInterface $organisations,
    ) {}

    #[Route('', name: 'app_admin_users', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $this->denyUnlessPlatformAdmin();

        $query = trim($request->query->getString('q'));
        $page = max(1, $request->query->getInt('page', 1));
        $offset = ($page - 1) * self::PAGE_SIZE;

        return $this->render('admin/users/index.html.twig', [
            'users' => $this->users->search($query, self::PAGE_SIZE, $offset),
            'total' => $this->users->countSearch($query),
            'query' => $query,
            'page' => $page,
        ]);
    }

    #[Route('/{id}', name: 'app_admin_user_show', methods: ['GET'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    public function show(string $id): Response
    {
        $this->denyUnlessPlatformAdmin();

        $userId = $this->userId($id);
        $user = $this->users->findById($userId) ?? throw new NotFoundHttpException('User not found.');
        $profile = $this->profiles->findByUserId($userId);

        return $this->render('admin/users/show.html.twig', [
            'user' => $user,
            'profile' => $profile,
            'assignments' => $this->roles->assignmentsForUser($userId),
            'roles' => $this->roles->listAll(),
            'organisations' => $this->organisations->forUser($userId->value()),
            'is_self' => $this->actorId() === $userId->value(),
        ]);
    }

    #[Route('/{id}/roles', name: 'app_admin_user_role_assign', methods: ['POST'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    public function assignRole(Request $request, string $id): Response
    {
        $this->denyUnlessPlatformAdmin();

        if (!$this->isCsrfTokenValid('assign_role', $request->request->getString('_csrf_token'))) {
            return new Response('Invalid CSRF token.', Response::HTTP_BAD_REQUEST);
        }

        try {
            ($this->assignRole)(new AssignRoleCommand(
                $this->actorId(),
                $id,
                $request->request->getString('role_code'),
                null,
            ));
        } catch (ConflictException $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('app_admin_user_show', ['id' => $id]);
        } catch (ValidationException $exception) {
            $this->addValidationFlash($exception);

            return $this->redirectToRoute('app_admin_user_show', ['id' => $id]);
        } catch (ForbiddenException) {
            throw new AccessDeniedHttpException('You are not allowed to assign roles.');
        }

        $this->addFlash('success', 'Role assigned.');

        return $this->redirectToRoute('app_admin_user_show', ['id' => $id]);
    }

    #[Route('/{id}/roles/revoke', name: 'app_admin_user_role_revoke', methods: ['POST'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    public function revokeRole(Request $request, string $id): Response
    {
        $this->denyUnlessPlatformAdmin();

        if (!$this->isCsrfTokenValid('revoke_role', $request->request->getString('_csrf_token'))) {
            return new Response('Invalid CSRF token.', Response::HTTP_BAD_REQUEST);
        }

        $userId = $this->userId($id);
        $roleCode = $request->request->getString('role_code');
        $organisationId = $request->request->getString('organisation_id');
        $organisationId = '' === $organisationId ? null : $organisationId;

        $assignmentExists = false;

        foreach ($this->roles->assignmentsForUser($userId) as $assignment) {
            if ($assignment['roleCode'] === $roleCode
                && $assignment['organisationId'] === $organisationId) {
                $assignmentExists = true;

                break;
            }
        }

        if (!$assignmentExists) {
            $this->addFlash('error', 'The role assignment no longer exists.');

            return $this->redirectToRoute('app_admin_user_show', ['id' => $id]);
        }

        try {
            ($this->revokeRole)(new RevokeRoleCommand($this->actorId(), $id, $roleCode, $organisationId));
        } catch (ConflictException $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('app_admin_user_show', ['id' => $id]);
        } catch (ValidationException $exception) {
            $this->addValidationFlash($exception);

            return $this->redirectToRoute('app_admin_user_show', ['id' => $id]);
        } catch (ForbiddenException) {
            throw new AccessDeniedHttpException('You are not allowed to revoke roles.');
        }

        $this->addFlash('success', 'Role revoked.');

        return $this->redirectToRoute('app_admin_user_show', ['id' => $id]);
    }

    #[Route('/{id}/deactivate', name: 'app_admin_user_deactivate', methods: ['POST'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    public function deactivate(Request $request, string $id): Response
    {
        $this->denyUnlessPlatformAdmin();

        if (!$this->isCsrfTokenValid('deactivate_user', $request->request->getString('_csrf_token'))) {
            return new Response('Invalid CSRF token.', Response::HTTP_BAD_REQUEST);
        }

        if ($this->actorId() === strtolower(trim($id))) {
            return new Response('You cannot deactivate your own account.', Response::HTTP_BAD_REQUEST);
        }

        try {
            ($this->deactivate)(new DeactivateUserCommand($this->actorId(), $id));
        } catch (ForbiddenException) {
            throw new AccessDeniedHttpException('You are not allowed to deactivate users.');
        } catch (UserNotFoundException $exception) {
            throw new NotFoundHttpException($exception->getMessage(), $exception);
        }

        $this->addFlash('success', 'The user account has been deactivated.');

        return $this->redirectToRoute('app_admin_user_show', ['id' => $id]);
    }

    private function denyUnlessPlatformAdmin(): void
    {
        if (!$this->isGranted('ROLE_PLATFORM_ADMIN')) {
            throw new AccessDeniedHttpException('Platform administrator access is required.');
        }
    }

    private function actorId(): string
    {
        $user = $this->getUser();

        if (!$user instanceof SecurityUser) {
            throw new AccessDeniedHttpException('An authenticated user is required.');
        }

        return $user->getDomainUser()->id()->value();
    }

    private function userId(string $value): UserId
    {
        try {
            return UserId::fromString($value);
        } catch (\InvalidArgumentException $exception) {
            throw new NotFoundHttpException('User not found.', $exception);
        }
    }

    private function addValidationFlash(ValidationException $exception): void
    {
        $messages = [];

        foreach ($exception->errors() as $fieldMessages) {
            foreach ($fieldMessages as $message) {
                $messages[] = $message;
            }
        }

        $this->addFlash('error', [] === $messages ? $exception->getMessage() : implode(' ', $messages));
    }
}
