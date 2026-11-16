<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Course\Infrastructure\Security;

use SovereignStack\Lms\Course\Application\Contract\HtmlSanitizerInterface;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Sanitises lesson HTML with a narrow allow-list of structural, textual and
 * media elements. Scripting elements, inline styles, event handler attributes
 * and dangerous URL schemes are removed; only href, src, alt and title may
 * survive on the elements that legitimately need them.
 */
final readonly class SymfonyHtmlSanitizer implements HtmlSanitizerInterface
{
    /**
     * @var array<string, list<string>>
     */
    private const ALLOWED_ELEMENTS = [
        'h1' => [],
        'h2' => [],
        'h3' => [],
        'h4' => [],
        'h5' => [],
        'h6' => [],
        'p' => [],
        'br' => [],
        'strong' => [],
        'em' => [],
        'ul' => [],
        'ol' => [],
        'li' => [],
        'table' => [],
        'thead' => [],
        'tbody' => [],
        'tr' => [],
        'th' => [],
        'td' => [],
        'a' => ['href', 'title'],
        'blockquote' => [],
        'code' => [],
        'pre' => [],
        'img' => ['src', 'alt', 'title'],
    ];

    /**
     * Elements whose content must never be rendered, even as plain text.
     *
     * @var list<string>
     */
    private const DROPPED_ELEMENTS = ['script', 'style', 'iframe', 'object', 'embed'];

    private HtmlSanitizer $sanitizer;

    public function __construct(bool $forceHttps = true)
    {
        $config = (new HtmlSanitizerConfig())
            ->allowLinkSchemes(['http', 'https', 'mailto'])
            ->allowMediaSchemes(['http', 'https'])
            ->forceHttpsUrls($forceHttps)
        ;

        foreach (self::ALLOWED_ELEMENTS as $element => $attributes) {
            $config = $config->allowElement($element, $attributes);
        }

        foreach (self::DROPPED_ELEMENTS as $element) {
            $config = $config->dropElement($element);
        }

        $this->sanitizer = new HtmlSanitizer($config);
    }

    public function sanitize(string $html): string
    {
        return $this->sanitizer->sanitize($html);
    }
}
