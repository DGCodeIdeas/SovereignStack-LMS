<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Identity\Presentation\Controller;

use SovereignStack\Lms\Identity\Application\Command\ResetPasswordCommand;
use SovereignStack\Lms\Identity\Application\Handler\ResetPasswordHandler;
use SovereignStack\Lms\Identity\Domain\Exception\TokenExpiredException;
use SovereignStack\Lms\Identity\Domain\Exception\TokenInvalidException;
use SovereignStack\Lms\Identity\Domain\Exception\UserNotFoundException;
use SovereignStack\Lms\Shared\Application\Exception\ValidationException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ResetPasswordController extends AbstractController
{
    #[Route('/reset-password/{token}', name: 'app_reset_password', methods: ['GET', 'POST'])]
    public function __invoke(string $token, Request $request, ResetPasswordHandler $handler): Response
    {
        $errors = [];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('reset_password', $request->request->getString('_csrf_token'))) {
                return new Response('Invalid CSRF token.', Response::HTTP_BAD_REQUEST);
            }

            $password = $request->request->getString('password');
            $confirmation = $request->request->getString('password_confirmation');

            if ($password !== $confirmation) {
                $errors = ['password_confirmation' => ['The password confirmation does not match.']];
            } else {
                try {
                    $handler(new ResetPasswordCommand($token, $password));

                    $this->addFlash('success', 'Your password has been reset. You can now sign in.');

                    return $this->redirectToRoute('app_login');
                } catch (TokenExpiredException|TokenInvalidException|UserNotFoundException) {
                    $this->addFlash('error', 'This password reset link is invalid or has expired.');

                    return $this->redirectToRoute('app_login');
                } catch (ValidationException $exception) {
                    $errors = $exception->errors();
                }
            }
        }

        return $this->render('security/reset.html.twig', [
            'token' => $token,
            'errors' => $errors,
        ]);
    }
}
