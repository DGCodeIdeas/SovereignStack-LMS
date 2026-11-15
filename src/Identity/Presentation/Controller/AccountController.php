<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Identity\Presentation\Controller;

use SovereignStack\Lms\Identity\Infrastructure\Security\SecurityUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AccountController extends AbstractController
{
    #[Route('/account', name: 'app_account', methods: ['GET'])]
    public function __invoke(): Response
    {
        $securityUser = $this->getUser();

        if (!$securityUser instanceof SecurityUser) {
            return $this->redirectToRoute('app_login');
        }

        $user = $securityUser->getDomainUser();

        return $this->render('account/index.html.twig', [
            'email' => $user->email()->value(),
            'status' => $user->status()->value,
            'roles' => $securityUser->getRoles(),
            'permissions' => $securityUser->getPermissions(),
        ]);
    }
}
