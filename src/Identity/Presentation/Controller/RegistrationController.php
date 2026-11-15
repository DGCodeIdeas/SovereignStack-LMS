<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Identity\Presentation\Controller;

use SovereignStack\Lms\Identity\Application\Command\RegisterUserCommand;
use SovereignStack\Lms\Identity\Application\Handler\RegisterUserHandler;
use SovereignStack\Lms\Identity\Domain\Exception\EmailAlreadyRegisteredException;
use SovereignStack\Lms\Shared\Application\Exception\ValidationException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function __invoke(Request $request, RegisterUserHandler $handler): Response
    {
        $errors = [];
        $email = '';
        $displayName = '';

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('register', $request->request->getString('_csrf_token'))) {
                return new Response('Invalid CSRF token.', Response::HTTP_BAD_REQUEST);
            }

            $email = $request->request->getString('email');
            $displayName = $request->request->getString('display_name');
            $password = $request->request->getString('password');
            $confirmation = $request->request->getString('password_confirmation');

            if ($password !== $confirmation) {
                $errors = ['password_confirmation' => ['The password confirmation does not match.']];
            } else {
                try {
                    $handler(new RegisterUserCommand($email, $password, $displayName));

                    $this->addFlash('success', 'Your account has been created. Check your email to verify your address.');

                    return $this->redirectToRoute('app_login');
                } catch (EmailAlreadyRegisteredException $exception) {
                    $errors = ['email' => [$exception->getMessage()]];
                } catch (ValidationException $exception) {
                    $errors = $exception->errors();
                }
            }
        }

        return $this->render('registration/register.html.twig', [
            'errors' => $errors,
            'email' => $email,
            'display_name' => $displayName,
        ]);
    }
}
