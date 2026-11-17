<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Enrollment\Application\Handler;

use SovereignStack\Lms\Enrollment\Application\Command\CancelEnrollmentCommand;
use SovereignStack\Lms\Enrollment\Application\Security\EnrollmentAccessPolicy;
use SovereignStack\Lms\Enrollment\Application\Support\Identifiers;
use SovereignStack\Lms\Enrollment\Domain\Exception\EnrollmentNotFoundException;
use SovereignStack\Lms\Enrollment\Domain\Repository\EnrollmentRepositoryInterface;
use SovereignStack\Lms\Shared\Application\Audit\AuditLoggerInterface;
use SovereignStack\Lms\Shared\Application\Outbox\OutboxWriterInterface;
use SovereignStack\Lms\Shared\Application\Transaction\TransactionManagerInterface;
use SovereignStack\Lms\Shared\Domain\Clock\ClockInterface;

final readonly class CancelEnrollmentHandler
{
    public function __construct(
        private EnrollmentAccessPolicy $policy,
        private EnrollmentRepositoryInterface $enrollments,
        private AuditLoggerInterface $audit,
        private OutboxWriterInterface $outbox,
        private TransactionManagerInterface $transactions,
        private ClockInterface $clock,
    ) {}

    public function __invoke(CancelEnrollmentCommand $command): void
    {
        $enrollmentId = Identifiers::enrollmentId($command->enrollmentId);
        $now = $this->clock->now();

        $this->transactions->transactional(function () use ($command, $enrollmentId, $now): void {
            $enrollment = $this->enrollments->findForUpdate($enrollmentId)
                ?? throw EnrollmentNotFoundException::forId($command->enrollmentId);

            $this->policy->requireSelfOrManager($command->actorUserId, $enrollment);

            $enrollment->cancel($now);
            $this->enrollments->save($enrollment);

            $this->audit->record(
                'enrollment.cancelled',
                $command->actorUserId,
                $enrollment->organisationId()->value(),
                'enrollment',
                $enrollment->id()->value(),
                [
                    'learner_user_id' => $enrollment->learnerUserId()->value(),
                    'course_id' => $enrollment->courseId(),
                ],
            );

            $this->outbox->write(
                'EnrollmentCancelled',
                'enrollment',
                $enrollment->id()->value(),
                $enrollment->organisationId()->value(),
                [
                    'enrollment_id' => $enrollment->id()->value(),
                    'learner_user_id' => $enrollment->learnerUserId()->value(),
                    'course_id' => $enrollment->courseId(),
                    'cancelled_at' => $now->format(\DATE_ATOM),
                ],
            );
        });
    }
}
