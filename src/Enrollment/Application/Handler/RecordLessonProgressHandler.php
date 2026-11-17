<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Enrollment\Application\Handler;

use SovereignStack\Lms\Enrollment\Application\Command\RecordLessonProgressCommand;
use SovereignStack\Lms\Enrollment\Application\Contract\EnrollmentCourseContentInterface;
use SovereignStack\Lms\Enrollment\Application\Contract\LessonProgressRecorderInterface;
use SovereignStack\Lms\Enrollment\Application\Contract\ProgressStatusInterface;
use SovereignStack\Lms\Enrollment\Application\Security\EnrollmentAccessPolicy;
use SovereignStack\Lms\Enrollment\Application\Support\Identifiers;
use SovereignStack\Lms\Enrollment\Domain\Exception\EnrollmentNotFoundException;
use SovereignStack\Lms\Enrollment\Domain\Repository\EnrollmentRepositoryInterface;
use SovereignStack\Lms\Shared\Application\Exception\ConflictException;
use SovereignStack\Lms\Shared\Application\Transaction\TransactionManagerInterface;
use SovereignStack\Lms\Shared\Domain\Clock\ClockInterface;

final readonly class RecordLessonProgressHandler
{
    private const MAX_ATTEMPTS = 3;
    private const RETRY_DELAYS = [10, 50, 100];

    public function __construct(
        private EnrollmentAccessPolicy $policy,
        private EnrollmentCourseContentInterface $content,
        private LessonProgressRecorderInterface $learning,
        private EnrollmentRepositoryInterface $enrollments,
        private TransactionManagerInterface $transactions,
        private ClockInterface $clock,
        private ProgressStatusInterface $progress,
    ) {}

    public function __invoke(RecordLessonProgressCommand $command): void
    {
        $enrollmentId = Identifiers::enrollmentId($command->enrollmentId);
        $target = $this->progress->parse($command->status);

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; ++$attempt) {
            try {
                $this->transactions->transactional(function () use ($command, $enrollmentId, $target): void {
                    $enrollment = $this->enrollments->findForUpdate($enrollmentId)
                        ?? throw EnrollmentNotFoundException::forId($command->enrollmentId);

                    $enrollment->assertActive();
                    $this->policy->requireSelfOrManager($command->actorUserId, $enrollment);

                    $moduleId = $this->content->moduleIdForLesson($command->lessonId, $enrollment->courseVersionId());

                    if (null === $moduleId) {
                        throw $this->content->lessonNotFound($command->lessonId);
                    }

                    $this->learning->recordLessonProgress(
                        $command->actorUserId,
                        $enrollmentId->value(),
                        $command->lessonId,
                        $target,
                        $enrollment->organisationId()->value(),
                        $moduleId,
                        $this->content->lessonIdsForModule($enrollment->courseVersionId(), $moduleId),
                        $this->clock->now(),
                    );
                });

                return;
            } catch (ConflictException $exception) {
                if (self::MAX_ATTEMPTS === $attempt) {
                    throw $exception;
                }

                usleep(self::RETRY_DELAYS[$attempt - 1] * 1000);
            }
        }
    }
}
