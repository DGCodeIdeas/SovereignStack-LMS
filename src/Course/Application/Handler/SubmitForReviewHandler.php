<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Course\Application\Handler;

use SovereignStack\Lms\Course\Application\Command\SubmitForReviewCommand;
use SovereignStack\Lms\Course\Application\Security\CourseAccessPolicy;
use SovereignStack\Lms\Course\Application\Support\CourseVersionResolver;
use SovereignStack\Lms\Course\Application\Support\Identifiers;
use SovereignStack\Lms\Course\Domain\Entity\CourseModule;
use SovereignStack\Lms\Course\Domain\Exception\CourseNotFoundException;
use SovereignStack\Lms\Course\Domain\Repository\CourseModuleRepositoryInterface;
use SovereignStack\Lms\Course\Domain\Repository\CourseRepositoryInterface;
use SovereignStack\Lms\Course\Domain\Repository\CourseVersionRepositoryInterface;
use SovereignStack\Lms\Shared\Application\Audit\AuditLoggerInterface;
use SovereignStack\Lms\Shared\Application\Exception\ValidationException;

final readonly class SubmitForReviewHandler
{
    public function __construct(
        private CourseAccessPolicy $policy,
        private CourseVersionResolver $versionResolver,
        private CourseRepositoryInterface $courses,
        private CourseModuleRepositoryInterface $modules,
        private CourseVersionRepositoryInterface $versions,
        private AuditLoggerInterface $audit,
    ) {}

    public function __invoke(SubmitForReviewCommand $command): void
    {
        $courseId = Identifiers::courseId($command->courseId);
        $course = $this->courses->findById($courseId) ?? throw CourseNotFoundException::forId($command->courseId);

        $this->policy->requireEdit($command->actorUserId, $course);

        $version = $this->versionResolver->requireDraft($courseId);
        $modules = $this->modules->listByVersion($version->id());

        if ([] === $modules) {
            throw ValidationException::withErrors([
                'modules' => ['A course needs at least one module before it can be submitted for review.'],
            ]);
        }

        $this->assertModuleTitles($modules);

        $version->submitForReview();
        $this->versions->save($version);

        $this->audit->record(
            'course.submitted_for_review',
            $command->actorUserId,
            $course->organisationId()->value(),
            'course',
            $courseId->value(),
            ['version_id' => $version->id()->value(), 'version_number' => $version->versionNumber()],
        );
    }

    /**
     * @param list<CourseModule> $modules
     */
    private function assertModuleTitles(array $modules): void
    {
        foreach ($modules as $module) {
            if ('' === trim($module->title())) {
                throw ValidationException::withErrors([
                    'modules' => [sprintf('Module "%s" must have a title.', $module->id()->value())],
                ]);
            }
        }
    }
}
