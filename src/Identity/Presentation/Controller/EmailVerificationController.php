<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Identity\Presentation\Controller;

use SovereignStack\Lms\Identity\Application\Command\VerifyEmailCommand;
use SovereignStack\Lms\Identity\Application\Handler\VerifyEmailHandler;
use SovereignStack\Lms\Identity\Domain\Exception\TokenExpiredException;
use SovereignStack\Lms\Identity\Domain\Exception\TokenInvalidException;
use SovereignStack\Lms\Identity\Domain\Exception\UserNotFoundException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class EmailVerificationController extends AbstractController
{
    #[Route('/verify/email/{token}', name: 'app_verify_email', methods: ['GET'])]
    public function __invoke(string $token, VerifyEmailHandler $handler): Response
    {
        try {
            $handler(new VerifyEmailCommand($token));
            $this->addFlash('success', 'Your email address has been verified. You can now sign in.');
        } catch (TokenExpiredException|TokenInvalidException|UserNotFoundException) {
            $this->addFlash('error', 'This verification link is invalid or has expired.');
        }

        return $this->redirectToRoute('app_login');
    }
}
