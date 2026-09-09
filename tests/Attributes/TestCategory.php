<?php

declare(strict_types=1);

namespace Tests\Attributes;

/**
 * Classify a test class by the kind of behaviour it verifies.
 *
 * Wave 3-01 requires every test class to carry an explicit category so
 * that coverage gaps (e.g. a critical flow with only pure-unit tests
 * but no HTTP or concurrency coverage) are visible at a glance.
 *
 * Categories:
 *
 *   pure-unit          — no DB, no HTTP; tests pure logic / value objects / DTOs
 *   service-integration — uses DB/Redis; tests service or repository layer
 *   http-feature       — makes HTTP requests through the Laravel test kernel
 *   docker-e2e         — exercises the real web server (openresty) via curl
 *   concurrency        — tests parallel / concurrent operations
 *   mutation           — Infection mutation testing target
 *   performance        — performance budget / query budget / k6
 *   architecture       — architecture ratchet / invariant test
 *
 * A test class may carry multiple categories (e.g. a concurrency test
 * that also makes HTTP requests). The primary category should be
 * listed first.
 *
 * @see https://docs.improvement-roadmap#w3-01
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class TestCategory
{
    /** @var list<string> */
    public readonly array $categories;

    /**
     * @param  string  ...$categories  One or more TestCategory::* constants.
     */
    public function __construct(string ...$categories)
    {
        $this->categories = array_values($categories);
    }

    public const PURE_UNIT = 'pure-unit';

    public const SERVICE_INTEGRATION = 'service-integration';

    public const HTTP_FEATURE = 'http-feature';

    public const DOCKER_E2E = 'docker-e2e';

    public const CONCURRENCY = 'concurrency';

    public const MUTATION = 'mutation';

    public const PERFORMANCE = 'performance';

    public const ARCHITECTURE = 'architecture';

    /**
     * All valid category values.
     *
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::PURE_UNIT,
            self::SERVICE_INTEGRATION,
            self::HTTP_FEATURE,
            self::DOCKER_E2E,
            self::CONCURRENCY,
            self::MUTATION,
            self::PERFORMANCE,
            self::ARCHITECTURE,
        ];
    }
}
