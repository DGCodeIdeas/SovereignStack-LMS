<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Identity\Presentation\Controller;

use SovereignStack\Lms\Identity\Application\Command\RequestPasswordResetCommand;
use SovereignStack\Lms\Identity\Application\Handler\RequestPasswordResetHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class RequestPasswordResetController extends AbstractController
{
    #[Route('/reset-password', name: 'app_request_password_reset', methods: ['GET', 'POST'])]
    public function __invoke(Request $request, RequestPasswordResetHandler $handler): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('request_password_reset', $request->request->getString('_csrf_token'))) {
                return new Response('Invalid CSRF token.', Response::HTTP_BAD_REQUEST);
            }

            $handler(new RequestPasswordResetCommand($request->request->getString('email')));

            $this->addFlash('success', 'If an account exists for that email address, a reset link has been sent.');

            return $this->redirectToRoute('app_request_password_reset');
        }

        return $this->render('security/request_reset.html.twig');
    }
}
