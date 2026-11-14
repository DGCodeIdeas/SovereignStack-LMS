<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal
 *
 * @coversNothing
 */
final class ProblemResponseTest extends DatabaseWebTestCase
{
    private const UUID_V7_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D';

    public function testValidationProblemResponse(): void
    {
        $client = $this->client();
        $client->request('GET', '/_test/problem/validation');

        $response = $client->getResponse();
        self::assertSame(400, $response->getStatusCode());

        $contentType = $response->headers->get('Content-Type');
        self::assertIsString($contentType);
        self::assertStringStartsWith('application/problem+json', $contentType);

        $body = $this->decode($client);

        self::assertSame(400, $body['status'] ?? null);
        self::assertSame('Validation failed', $body['title'] ?? null);
        self::assertSame('https://sovereignstack.dev/problems/validation-failed', $body['type'] ?? null);

        $errors = $body['errors'] ?? null;
        self::assertIsArray($errors);
        self::assertSame(['Email is invalid.'], $errors['email'] ?? null);
        self::assertArrayHasKey('correlation_id', $body);
        self::assertArrayNotHasKey('trace', $body);
    }

    public function testNotFoundProblemResponse(): void
    {
        $this->assertProblem(404, '/_test/problem/not_found', 'https://sovereignstack.dev/problems/not-found', 'Not Found');
    }

    public function testConflictProblemResponse(): void
    {
        $this->assertProblem(409, '/_test/problem/conflict', 'https://sovereignstack.dev/problems/conflict', 'Conflict');
    }

    public function testForbiddenProblemResponse(): void
    {
        $this->assertProblem(403, '/_test/problem/forbidden', 'https://sovereignstack.dev/problems/forbidden', 'Forbidden');
    }

    public function testDomainProblemResponse(): void
    {
        $this->assertProblem(422, '/_test/problem/domain', 'https://sovereignstack.dev/problems/domain-violation', 'Unprocessable Entity');
    }

    public function testUnexpectedProblemResponseDoesNotLeakInternals(): void
    {
        $client = $this->client();
        $client->request('GET', '/_test/problem/unexpected');

        $response = $client->getResponse();
        self::assertSame(500, $response->getStatusCode());

        $body = $this->decode($client);

        self::assertSame(500, $body['status'] ?? null);
        self::assertSame('An unexpected error occurred.', $body['detail'] ?? null);
        self::assertSame('https://sovereignstack.dev/problems/internal-error', $body['type'] ?? null);
        self::assertArrayHasKey('correlation_id', $body);

        foreach (['SECRET_INTERNAL_DETAIL', 'RuntimeException', '/srv/private/path.php', 'trace', 'file', 'line'] as $needle) {
            self::assertStringNotContainsString($needle, (string) $response->getContent());
        }

        self::assertArrayNotHasKey('trace', $body);
        self::assertArrayNotHasKey('file', $body);
        self::assertArrayNotHasKey('line', $body);
    }

    public function testCorrelationIdIsPropagatedToSuccessfulResponse(): void
    {
        $client = $this->client();
        $client->request('GET', '/health', server: ['HTTP_X_CORRELATION_ID' => 'test-correlation-123']);

        self::assertResponseIsSuccessful();
        self::assertSame('test-correlation-123', $client->getResponse()->headers->get('X-Correlation-ID'));
    }

    public function testMissingCorrelationIdHeaderIsGenerated(): void
    {
        $client = $this->client();
        $client->request('GET', '/health');

        $correlationId = $client->getResponse()->headers->get('X-Correlation-ID');
        self::assertIsString($correlationId);
        self::assertMatchesRegularExpression(self::UUID_V7_PATTERN, $correlationId);
    }

    public function testInvalidCorrelationIdHeaderIsReplaced(): void
    {
        $client = $this->client();
        $client->request('GET', '/health', server: ['HTTP_X_CORRELATION_ID' => 'bad id with spaces']);

        $correlationId = $client->getResponse()->headers->get('X-Correlation-ID');
        self::assertIsString($correlationId);
        self::assertNotSame('bad id with spaces', $correlationId);
        self::assertMatchesRegularExpression(self::UUID_V7_PATTERN, $correlationId);
    }

    public function testProblemResponseEchoesValidCorrelationId(): void
    {
        $client = $this->client();
        $client->request('GET', '/_test/problem/not_found', server: ['HTTP_X_CORRELATION_ID' => 'problem-correlation-123']);

        self::assertSame('problem-correlation-123', $client->getResponse()->headers->get('X-Correlation-ID'));

        $body = $this->decode($client);
        self::assertSame('problem-correlation-123', $body['correlation_id'] ?? null);
    }

    private function assertProblem(int $status, string $url, string $type, string $title): void
    {
        $client = $this->client();
        $client->request('GET', $url);

        $response = $client->getResponse();
        self::assertSame($status, $response->getStatusCode());

        $body = $this->decode($client);

        self::assertSame($status, $body['status'] ?? null);
        self::assertSame($type, $body['type'] ?? null);
        self::assertSame($title, $body['title'] ?? null);
        self::assertSame($url, $body['instance'] ?? null);
        self::assertArrayHasKey('correlation_id', $body);
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(KernelBrowser $client): array
    {
        $content = $client->getResponse()->getContent();
        self::assertIsString($content);

        $data = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        return $data;
    }
}
