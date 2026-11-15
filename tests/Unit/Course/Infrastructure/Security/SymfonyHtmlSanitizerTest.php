<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Unit\Course\Infrastructure\Security;

use PHPUnit\Framework\TestCase;
use SovereignStack\Lms\Course\Infrastructure\Security\SymfonyHtmlSanitizer;

/**
 * @internal
 *
 * @coversNothing
 */
final class SymfonyHtmlSanitizerTest extends TestCase
{
    public function testRemovesScriptsHandlersAndDangerousSchemes(): void
    {
        $output = (new SymfonyHtmlSanitizer())->sanitize($this->hostileDocument());

        foreach (['<script', '</script', 'alert(', '<iframe', '<object', '<embed', 'onclick', 'onerror', 'javascript:', 'data:image', 'class='] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $output);
        }
    }

    public function testKeepsAllowedStructureTextAndLinks(): void
    {
        $output = (new SymfonyHtmlSanitizer())->sanitize($this->hostileDocument());

        self::assertStringContainsString('<h2>Heading</h2>', $output);
        self::assertStringContainsString('<p>Body <strong>bold</strong> <em>italic</em></p>', $output);
        self::assertStringContainsString('<a href="https://example.com/page" title="ok">Link</a>', $output);
        self::assertStringContainsString('<ul><li>One</li></ul>', $output);
        self::assertStringContainsString('<table>', $output);
        self::assertStringContainsString('<th>H</th>', $output);
        self::assertStringContainsString('<td>D</td>', $output);
        self::assertStringContainsString('<img src="https://cdn.example.com/a.png" alt="Alt" />', $output);
        self::assertStringContainsString('<a>Bad</a>', $output);
    }

    public function testForcesHttpsForLinksAndMedia(): void
    {
        $output = (new SymfonyHtmlSanitizer(true))->sanitize('<a href="http://example.com">Link</a><img src="http://cdn.example.com/a.png" alt="A">');

        self::assertStringContainsString('href="https://example.com"', $output);
        self::assertStringContainsString('src="https://cdn.example.com/a.png"', $output);
    }

    public function testKeepsHttpWhenHttpsIsNotForced(): void
    {
        $output = (new SymfonyHtmlSanitizer(false))->sanitize('<a href="http://example.com">Link</a>');

        self::assertStringContainsString('href="http://example.com"', $output);
    }

    public function testAllowsMailtoLinksOnly(): void
    {
        $output = (new SymfonyHtmlSanitizer())->sanitize('<a href="mailto:hello@example.com">Mail</a><a href="ftp://example.com">Ftp</a>');

        self::assertStringContainsString('href="mailto:hello&#64;example.com"', $output);
        self::assertStringNotContainsString('ftp:', $output);
    }

    private function hostileDocument(): string
    {
        return <<<'HTML'
            <script>alert('x')</script>
            <style>body{color:red}</style>
            <iframe src="https://example.com"></iframe>
            <h2 onclick="evil()">Heading</h2>
            <p class="lead">Body <strong>bold</strong> <em>italic</em></p>
            <a href="https://example.com/page" title="ok">Link</a>
            <a href="javascript:alert(1)">Bad</a>
            <img src="http://cdn.example.com/a.png" alt="Alt" onerror="evil()">
            <img src="data:image/png;base64,AAAA" alt="Bad">
            <ul><li>One</li></ul>
            <table><thead><tr><th>H</th></tr></thead><tbody><tr><td>D</td></tr></tbody></table>
            <object data="x"></object><embed src="x">
            HTML;
    }
}
