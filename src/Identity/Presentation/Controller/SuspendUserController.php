<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Identity\Presentation\Controller;

use SovereignStack\Lms\Identity\Application\Command\SuspendUserCommand;
use SovereignStack\Lms\Identity\Application\Handler\SuspendUserHandler;
use SovereignStack\Lms\Identity\Infrastructure\Security\SecurityUser;
use SovereignStack\Lms\Shared\Application\Exception\ForbiddenException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SuspendUserController extends AbstractController
{
    #[Route('/admin/users/{id}/suspend', name: 'app_admin_suspend_user', methods: ['POST'])]
    public function __invoke(string $id, Request $request, SuspendUserHandler $handler): Response
    {
        $securityUser = $this->getUser();

        if (!$securityUser instanceof SecurityUser) {
            return new Response('Forbidden', Response::HTTP_FORBIDDEN);
        }

        if (!$this->isCsrfTokenValid('suspend_user', $request->request->getString('_csrf_token'))) {
            return new Response('Invalid CSRF token.', Response::HTTP_BAD_REQUEST);
        }

        if ($securityUser->getDomainUser()->id()->value() === strtolower(trim($id))) {
            return new Response('You cannot suspend your own account.', Response::HTTP_BAD_REQUEST);
        }

        try {
            $handler(new SuspendUserCommand($securityUser->getDomainUser()->id()->value(), $id));
        } catch (ForbiddenException) {
            return new Response('Forbidden', Response::HTTP_FORBIDDEN);
        }

        $this->addFlash('success', 'The user account has been suspended.');

        if ($this->isGranted('ROLE_PLATFORM_ADMIN')) {
            return $this->redirectToRoute('app_admin_user_show', ['id' => $id]);
        }

        return $this->redirectToRoute('app_account');
    }
}
