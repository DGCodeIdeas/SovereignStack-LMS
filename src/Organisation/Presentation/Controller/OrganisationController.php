<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Organisation\Presentation\Controller;

use SovereignStack\Lms\Organisation\Application\Command\AddMembershipCommand;
use SovereignStack\Lms\Organisation\Application\Command\CreateOrganisationCommand;
use SovereignStack\Lms\Organisation\Application\Command\EndMembershipCommand;
use SovereignStack\Lms\Organisation\Application\Handler\AddMembershipHandler;
use SovereignStack\Lms\Organisation\Application\Handler\CreateOrganisationHandler;
use SovereignStack\Lms\Organisation\Application\Handler\EndMembershipHandler;
use SovereignStack\Lms\Organisation\Application\Handler\ListOrganisationMembersHandler;
use SovereignStack\Lms\Organisation\Application\Query\ListOrganisationMembersQuery;
use SovereignStack\Lms\Organisation\Domain\Entity\Organisation;
use SovereignStack\Lms\Organisation\Domain\Repository\MembershipRepositoryInterface;
use SovereignStack\Lms\Organisation\Domain\Repository\OrganisationRepositoryInterface;
use SovereignStack\Lms\Organisation\Domain\ValueObject\OrganisationId;
use SovereignStack\Lms\Shared\Application\Exception\ConflictException;
use SovereignStack\Lms\Shared\Application\Exception\ForbiddenException;
use SovereignStack\Lms\Shared\Application\Exception\NotFoundException;
use SovereignStack\Lms\Shared\Application\Exception\ValidationException;
use SovereignStack\Lms\Shared\Domain\Security\DomainUserAwareInterface;
use SovereignStack\Lms\Shared\Domain\ValueObject\UserIdReference;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class OrganisationController extends AbstractController
{
    public function __construct(
        private readonly OrganisationRepositoryInterface $organisations,
        private readonly MembershipRepositoryInterface $memberships,
        private readonly CreateOrganisationHandler $createOrganisation,
        private readonly AddMembershipHandler $addMembership,
        private readonly EndMembershipHandler $endMembership,
        private readonly ListOrganisationMembersHandler $listMembers,
    ) {}

    #[Route('/organisations', name: 'app_organisations', methods: ['GET'])]
    public function index(): Response
    {
        $organisations = $this->organisations->listForUser(
            UserIdReference::fromString($this->actorId()),
        );

        $memberCounts = [];

        foreach ($organisations as $organisation) {
            $memberCounts[$organisation->id()->value()] = \count(
                $this->memberships->listForOrganisation($organisation->id()),
            );
        }

        return $this->render('organisation/index.html.twig', [
            'organisations' => $organisations,
            'memberCounts' => $memberCounts,
        ]);
    }

    #[Route('/organisations', name: 'app_organisation_create', methods: ['POST'])]
    public function create(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('create_organisation', $request->request->getString('_csrf_token'))) {
            return new Response('Invalid CSRF token.', Response::HTTP_BAD_REQUEST);
        }

        $actorId = $this->actorId();

        try {
            $organisationId = ($this->createOrganisation)(new CreateOrganisationCommand(
                $actorId,
                $request->request->getString('name'),
                $request->request->getString('slug'),
            ));
        } catch (ForbiddenException) {
            throw new AccessDeniedHttpException('You are not allowed to create organisations.');
        } catch (ConflictException $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('app_organisations');
        } catch (ValidationException $exception) {
            $this->addValidationFlash($exception);

            return $this->redirectToRoute('app_organisations');
        }

        $this->addFlash('success', 'Organisation created.');

        return $this->redirectToRoute('app_organisation_members', [
            'organisationId' => $organisationId->value(),
        ]);
    }

    #[Route('/organisations/{organisationId}/members', name: 'app_organisation_members', methods: ['GET'])]
    public function members(string $organisationId): Response
    {
        $actorId = $this->actorId();

        try {
            $members = ($this->listMembers)(new ListOrganisationMembersQuery($actorId, $organisationId));
        } catch (ForbiddenException) {
            throw new AccessDeniedHttpException('You are not allowed to view this organisation.');
        } catch (NotFoundException $exception) {
            throw new NotFoundHttpException($exception->getMessage(), $exception);
        }

        $organisation = $this->findOrganisation($organisationId)
            ?? throw new NotFoundHttpException('Organisation not found.');

        return $this->render('organisation/members.html.twig', [
            'organisation' => $organisation,
            'members' => $members,
        ]);
    }

    #[Route('/organisations/{organisationId}/members', name: 'app_organisation_member_add', methods: ['POST'])]
    public function addMember(Request $request, string $organisationId): Response
    {
        if (!$this->isCsrfTokenValid('add_membership', $request->request->getString('_csrf_token'))) {
            return new Response('Invalid CSRF token.', Response::HTTP_BAD_REQUEST);
        }

        $actorId = $this->actorId();

        try {
            ($this->addMembership)(new AddMembershipCommand(
                $actorId,
                $organisationId,
                $request->request->getString('user_id'),
            ));
        } catch (ForbiddenException) {
            throw new AccessDeniedHttpException('You are not allowed to manage this organisation.');
        } catch (NotFoundException $exception) {
            throw new NotFoundHttpException($exception->getMessage(), $exception);
        } catch (ConflictException $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToMembers($organisationId);
        } catch (ValidationException $exception) {
            $this->addValidationFlash($exception);

            return $this->redirectToMembers($organisationId);
        }

        $this->addFlash('success', 'Member added.');

        return $this->redirectToMembers($organisationId);
    }

    #[Route('/organisations/{organisationId}/members/{userId}/end', name: 'app_organisation_member_end', methods: ['POST'])]
    public function endMember(Request $request, string $organisationId, string $userId): Response
    {
        if (!$this->isCsrfTokenValid('end_membership', $request->request->getString('_csrf_token'))) {
            return new Response('Invalid CSRF token.', Response::HTTP_BAD_REQUEST);
        }

        $actorId = $this->actorId();

        try {
            ($this->endMembership)(new EndMembershipCommand($actorId, $organisationId, $userId));
        } catch (ForbiddenException) {
            throw new AccessDeniedHttpException('You are not allowed to manage this organisation.');
        } catch (NotFoundException $exception) {
            throw new NotFoundHttpException($exception->getMessage(), $exception);
        }

        $this->addFlash('success', 'Membership ended.');

        return $this->redirectToMembers($organisationId);
    }

    private function actorId(): string
    {
        $user = $this->getUser();

        if (!$user instanceof DomainUserAwareInterface) {
            throw new AccessDeniedHttpException('An authenticated user is required.');
        }

        return $user->domainUserId();
    }

    private function findOrganisation(string $organisationId): ?Organisation
    {
        try {
            return $this->organisations->findById(OrganisationId::fromString($organisationId));
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    private function redirectToMembers(string $organisationId): Response
    {
        return $this->redirectToRoute('app_organisation_members', [
            'organisationId' => $organisationId,
        ]);
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
