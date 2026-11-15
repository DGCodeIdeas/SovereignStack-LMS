<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Identity\Presentation\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    #[Route('/', name: 'app_dashboard', methods: ['GET'])]
    public function __invoke(): Response
    {
        return $this->redirectToRoute(null === $this->getUser() ? 'app_login' : 'app_account');
    }
}
