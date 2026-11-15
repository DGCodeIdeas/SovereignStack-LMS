<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Identity\Presentation\Controller;

use SovereignStack\Lms\Identity\Application\Command\ChangePasswordCommand;
use SovereignStack\Lms\Identity\Application\Handler\ChangePasswordHandler;
use SovereignStack\Lms\Identity\Infrastructure\Security\SecurityUser;
use SovereignStack\Lms\Shared\Application\Exception\ForbiddenException;
use SovereignStack\Lms\Shared\Application\Exception\ValidationException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ChangePasswordController extends AbstractController
{
    #[Route('/account/password', name: 'app_change_password', methods: ['GET', 'POST'])]
    public function __invoke(Request $request, ChangePasswordHandler $handler): Response
    {
        $securityUser = $this->getUser();

        if (!$securityUser instanceof SecurityUser) {
            return $this->redirectToRoute('app_login');
        }

        $errors = [];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('change_password', $request->request->getString('_csrf_token'))) {
                return new Response('Invalid CSRF token.', Response::HTTP_BAD_REQUEST);
            }

            $newPassword = $request->request->getString('new_password');
            $confirmation = $request->request->getString('new_password_confirmation');

            if ($newPassword !== $confirmation) {
                $errors = ['new_password_confirmation' => ['The password confirmation does not match.']];
            } else {
                try {
                    $handler(new ChangePasswordCommand(
                        $securityUser->getDomainUser()->id()->value(),
                        $request->request->getString('current_password'),
                        $newPassword,
                    ));

                    $request->getSession()->invalidate();
                    $this->addFlash('success', 'Your password has been changed. Please sign in again.');

                    return $this->redirectToRoute('app_login');
                } catch (ForbiddenException $exception) {
                    $errors = ['current_password' => [$exception->getMessage()]];
                } catch (ValidationException $exception) {
                    $errors = $exception->errors();
                }
            }
        }

        return $this->render('account/password.html.twig', [
            'errors' => $errors,
        ]);
    }
}
