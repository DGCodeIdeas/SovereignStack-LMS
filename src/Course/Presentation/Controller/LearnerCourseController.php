<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Course\Presentation\Controller;

use SovereignStack\Lms\Course\Application\Contract\LearnerCourseCatalogInterface;
use SovereignStack\Lms\Course\Application\Contract\UserOrganisationsInterface;
use SovereignStack\Lms\Shared\Domain\Security\DomainUserAwareInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class LearnerCourseController extends AbstractController
{
    private const COURSE_ID_REQUIREMENT = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}';

    public function __construct(
        private readonly LearnerCourseCatalogInterface $catalog,
        private readonly UserOrganisationsInterface $organisations,
    ) {}

    #[Route('/learn/courses', name: 'app_learn_courses', methods: ['GET'])]
    public function index(): Response
    {
        $userId = $this->actorId();
        $organisations = $this->organisations->forUser($userId);
        $organisationIds = array_map(
            static fn (array $organisation): string => $organisation['id'],
            $organisations,
        );
        $organisationNames = [];

        foreach ($organisations as $organisation) {
            $organisationNames[$organisation['id']] = $organisation['name'];
        }

        return $this->render('learn/index.html.twig', [
            'courses' => $this->catalog->list($userId, $organisationIds),
            'organisationNames' => $organisationNames,
        ]);
    }

    #[Route(
        '/learn/courses/{courseId}',
        name: 'app_learn_course',
        methods: ['GET'],
        requirements: ['courseId' => self::COURSE_ID_REQUIREMENT],
    )]
    public function show(string $courseId): Response
    {
        $userId = $this->actorId();
        $course = $this->catalog->view($courseId, $this->organisationIds($userId));

        if (null === $course) {
            throw new NotFoundHttpException('The course was not found.');
        }

        return $this->render('learn/show.html.twig', [
            'course' => $course,
        ]);
    }

    private function actorId(): string
    {
        $user = $this->getUser();

        if (!$user instanceof DomainUserAwareInterface) {
            throw new AccessDeniedHttpException('An authenticated user is required.');
        }

        return $user->domainUserId();
    }

    /**
     * @return list<string>
     */
    private function organisationIds(string $userId): array
    {
        return array_map(
            static fn (array $organisation): string => $organisation['id'],
            $this->organisations->forUser($userId),
        );
    }
}
