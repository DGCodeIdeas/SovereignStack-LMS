<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Course\Application\Contract;

interface LearnerCourseCatalogInterface
{
    /**
     * Published courses the learner may browse in the catalogue.
     *
     * Public courses are listed for every learner, while organisation-only
     * courses require an active organisation in the provided list. Restricted
     * and archived courses are never listed.
     *
     * @param list<string> $organisationIds
     *
     * @return list<array{
     *     id: string,
     *     slug: string,
     *     title: string,
     *     description: null|string,
     *     organisationId: string,
     *     visibility: string,
     *     moduleCount: int
     * }>
     */
    public function list(string $userId, array $organisationIds): array;

    /**
     * Published course detail for the learner catalogue.
     *
     * Returns null when the course is missing, unpublished, archived,
     * restricted, or organisation-only outside the provided organisations.
     *
     * @param list<string> $organisationIds
     *
     * @return null|array{
     *     courseId: string,
     *     title: string,
     *     description: null|string,
     *     modules: list<array{
     *         id: string,
     *         title: string,
     *         isRequired: bool,
     *         lessons: list<array{id: string, title: string}>
     *     }>
     * }
     */
    public function view(string $courseId, array $organisationIds): ?array;
}
