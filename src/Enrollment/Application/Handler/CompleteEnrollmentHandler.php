<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Enrollment\Application\Handler;

use SovereignStack\Lms\Enrollment\Application\Command\CompleteEnrollmentCommand;
use SovereignStack\Lms\Enrollment\Application\Contract\CompletionErrorsInterface;
use SovereignStack\Lms\Enrollment\Application\Contract\EnrollmentCourseContentInterface;
use SovereignStack\Lms\Enrollment\Application\Contract\LearningProgressInterface;
use SovereignStack\Lms\Enrollment\Application\Security\EnrollmentAccessPolicy;
use SovereignStack\Lms\Enrollment\Application\Support\Identifiers;
use SovereignStack\Lms\Enrollment\Domain\Entity\CourseCompletion;
use SovereignStack\Lms\Enrollment\Domain\Enum\EnrollmentStatus;
use SovereignStack\Lms\Enrollment\Domain\Exception\EnrollmentNotFoundException;
use SovereignStack\Lms\Enrollment\Domain\Repository\CourseCompletionRepositoryInterface;
use SovereignStack\Lms\Enrollment\Domain\Repository\EnrollmentRepositoryInterface;
use SovereignStack\Lms\Enrollment\Domain\ValueObject\CourseCompletionId;
use SovereignStack\Lms\Shared\Application\Audit\AuditLoggerInterface;
use SovereignStack\Lms\Shared\Application\Outbox\OutboxWriterInterface;
use SovereignStack\Lms\Shared\Application\Transaction\TransactionManagerInterface;
use SovereignStack\Lms\Shared\Domain\Clock\ClockInterface;
use SovereignStack\Lms\Shared\Domain\Uuid\UuidGeneratorInterface;
use SovereignStack\Lms\Shared\Domain\ValueObject\UserIdReference;

final readonly class CompleteEnrollmentHandler
{
    public function __construct(
        private EnrollmentAccessPolicy $policy,
        private EnrollmentCourseContentInterface $content,
        private CompletionErrorsInterface $completionErrors,
        private LearningProgressInterface $learningProgress,
        private EnrollmentRepositoryInterface $enrollments,
        private CourseCompletionRepositoryInterface $completions,
        private AuditLoggerInterface $audit,
        private OutboxWriterInterface $outbox,
        private TransactionManagerInterface $transactions,
        private UuidGeneratorInterface $uuid,
        private ClockInterface $clock,
    ) {}

    public function __invoke(CompleteEnrollmentCommand $command): CourseCompletionId
    {
        $enrollmentId = Identifiers::enrollmentId($command->enrollmentId);

        return $this->transactions->transactional(function () use ($command, $enrollmentId): CourseCompletionId {
            $enrollment = $this->enrollments->findForUpdate($enrollmentId)
                ?? throw EnrollmentNotFoundException::forId($command->enrollmentId);

            $this->policy->requireSelfOrAdmin($command->actorUserId, $enrollment);

            $existing = $this->completions->findByEnrollment($enrollmentId->value());

            if (EnrollmentStatus::COMPLETED === $enrollment->status() && null !== $existing) {
                return $existing->id();
            }

            $enrollment->assertActive();

            $required = $this->content->requiredModules($enrollment->courseVersionId());

            if ([] === $required) {
                throw $this->completionErrors->emptyCourse($enrollment->courseVersionId());
            }

            $incomplete = $this->learningProgress->incompleteRequiredModuleIds(
                $enrollmentId->value(),
                $enrollment->courseVersionId(),
            );

            if ([] !== $incomplete) {
                throw $this->completionErrors->incompleteModules($incomplete);
            }

            if (null !== $existing) {
                return $existing->id();
            }

            $now = $this->clock->now();
            $completion = CourseCompletion::record(
                CourseCompletionId::fromString($this->uuid->generate()),
                $enrollmentId->value(),
                UserIdReference::fromString($enrollment->learnerUserId()->value()),
                $enrollment->courseId(),
                $enrollment->courseVersionId(),
                $now,
            );

            $this->completions->save($completion);
            $enrollment->complete($now);
            $this->enrollments->save($enrollment);

            $this->audit->record(
                'enrollment.completed',
                $command->actorUserId,
                $enrollment->organisationId()->value(),
                'enrollment',
                $enrollment->id()->value(),
                [
                    'learner_user_id' => $enrollment->learnerUserId()->value(),
                    'course_id' => $enrollment->courseId(),
                    'course_version_id' => $enrollment->courseVersionId(),
                    'completion_id' => $completion->id()->value(),
                ],
            );

            $this->outbox->write(
                'EnrollmentCompleted',
                'enrollment',
                $enrollment->id()->value(),
                $enrollment->organisationId()->value(),
                [
                    'enrollment_id' => $enrollment->id()->value(),
                    'learner_user_id' => $enrollment->learnerUserId()->value(),
                    'course_id' => $enrollment->courseId(),
                    'course_version_id' => $enrollment->courseVersionId(),
                    'completion_id' => $completion->id()->value(),
                ],
            );

            return $completion->id();
        });
    }
}
