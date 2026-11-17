<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Enrollment\Application\Handler;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use SovereignStack\Lms\Enrollment\Application\Command\EnrollCommand;
use SovereignStack\Lms\Enrollment\Application\Contract\EnrollmentCourseContentInterface;
use SovereignStack\Lms\Enrollment\Application\Contract\MembershipInterface;
use SovereignStack\Lms\Enrollment\Application\Security\EnrollmentAccessPolicy;
use SovereignStack\Lms\Enrollment\Domain\Entity\Enrollment;
use SovereignStack\Lms\Enrollment\Domain\Exception\AlreadyEnrolledException;
use SovereignStack\Lms\Enrollment\Domain\Exception\CourseNotPublishedException;
use SovereignStack\Lms\Enrollment\Domain\Repository\EnrollmentRepositoryInterface;
use SovereignStack\Lms\Enrollment\Domain\ValueObject\EnrollmentId;
use SovereignStack\Lms\Shared\Application\Audit\AuditLoggerInterface;
use SovereignStack\Lms\Shared\Application\Exception\ForbiddenException;
use SovereignStack\Lms\Shared\Application\Outbox\OutboxWriterInterface;
use SovereignStack\Lms\Shared\Application\Transaction\TransactionManagerInterface;
use SovereignStack\Lms\Shared\Domain\Clock\ClockInterface;
use SovereignStack\Lms\Shared\Domain\Uuid\UuidGeneratorInterface;
use SovereignStack\Lms\Shared\Domain\ValueObject\OrganisationIdReference;
use SovereignStack\Lms\Shared\Domain\ValueObject\UserIdReference;

final readonly class EnrollHandler
{
    public function __construct(
        private EnrollmentAccessPolicy $policy,
        private EnrollmentCourseContentInterface $content,
        private MembershipInterface $memberships,
        private EnrollmentRepositoryInterface $enrollments,
        private AuditLoggerInterface $audit,
        private OutboxWriterInterface $outbox,
        private TransactionManagerInterface $transactions,
        private UuidGeneratorInterface $uuid,
        private ClockInterface $clock,
    ) {}

    public function __invoke(EnrollCommand $command): EnrollmentId
    {
        $version = $this->content->publishedVersionForCourse($command->courseId);

        if (null === $version) {
            throw CourseNotPublishedException::forCourse($command->courseId);
        }

        $this->policy->requireCreate($command->actorUserId, $command->learnerUserId, $version->organisationId);

        $actorIsAdmin = $this->policy->isAdmin($command->actorUserId, $version->organisationId);
        $actorIsManager = $this->policy->isManager($command->actorUserId, $version->organisationId);
        $visibility = $version->visibility;

        if ('restricted' === $visibility && !$actorIsManager) {
            throw new ForbiddenException('Only staff can enroll in this restricted course.');
        }

        if ('archived' === $visibility) {
            throw CourseNotPublishedException::forCourse($command->courseId);
        }

        if ('public' !== $visibility
            && !$actorIsAdmin
            && !$this->memberships->isActiveMember($command->learnerUserId, $version->organisationId)) {
            throw new ForbiddenException('The learner must be an active member of the organisation to enroll.');
        }

        $now = $this->clock->now();
        $enrollment = Enrollment::create(
            EnrollmentId::fromString($this->uuid->generate()),
            OrganisationIdReference::fromString($version->organisationId),
            UserIdReference::fromString($command->learnerUserId),
            $version->courseId,
            $version->versionId,
            $now,
        );

        try {
            $this->transactions->transactional(function () use ($enrollment, $version): void {
                $this->enrollments->save($enrollment);
                $this->audit->record(
                    'enrollment.created',
                    $enrollment->learnerUserId()->value(),
                    $version->organisationId,
                    'enrollment',
                    $enrollment->id()->value(),
                    [
                        'learner_user_id' => $enrollment->learnerUserId()->value(),
                        'course_id' => $enrollment->courseId(),
                        'course_version_id' => $enrollment->courseVersionId(),
                    ],
                );
                $this->outbox->write(
                    'EnrollmentCreated',
                    'enrollment',
                    $enrollment->id()->value(),
                    $version->organisationId,
                    [
                        'enrollment_id' => $enrollment->id()->value(),
                        'learner_user_id' => $enrollment->learnerUserId()->value(),
                        'course_id' => $enrollment->courseId(),
                        'course_version_id' => $enrollment->courseVersionId(),
                    ],
                );
            });
        } catch (UniqueConstraintViolationException) {
            throw AlreadyEnrolledException::forLearnerAndCourse($command->learnerUserId, $version->courseId);
        }

        return $enrollment->id();
    }
}
