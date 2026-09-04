<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\LegacyHeaderBag;
use Tests\TestCase;

/**
 * Unit tests for LegacyHeaderBag — per-request replacement for
 * PHP SAPI globals headers_list()/http_response_code()/header_remove().
 */
final class LegacyHeaderBagTest extends TestCase
{
    private LegacyHeaderBag $bag;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bag = new LegacyHeaderBag;
    }

    public function test_set_and_get_header(): void
    {
        $this->bag->set('Location', '/index.php');

        $this->assertTrue($this->bag->has('Location'));
        $this->assertSame(['/index.php'], $this->bag->get('Location'));
        $this->assertSame('/index.php', $this->bag->first('Location'));
    }

    public function test_set_replaces_existing_value(): void
    {
        $this->bag->set('Content-Type', 'text/html');
        $this->bag->set('Content-Type', 'image/png');

        $this->assertSame(['image/png'], $this->bag->get('Content-Type'));
    }

    public function test_add_appends_value(): void
    {
        $this->bag->add('Set-Cookie', 'a=1');
        $this->bag->add('Set-Cookie', 'b=2');

        $this->assertSame(['a=1', 'b=2'], $this->bag->get('Set-Cookie'));
    }

    public function test_remove_header(): void
    {
        $this->bag->set('Location', '/index.php');
        $this->bag->remove('Location');

        $this->assertFalse($this->bag->has('Location'));
        $this->assertNull($this->bag->first('Location'));
    }

    public function test_case_insensitive_lookup(): void
    {
        $this->bag->set('Content-Type', 'text/html');

        $this->assertTrue($this->bag->has('content-type'));
        $this->assertTrue($this->bag->has('CONTENT-TYPE'));
        $this->assertSame('text/html', $this->bag->first('content-type'));
    }

    public function test_set_and_get_status_code(): void
    {
        $this->bag->setStatusCode(302);

        $this->assertSame(302, $this->bag->getStatusCode());
    }

    public function test_status_code_defaults_to_null(): void
    {
        $this->assertNull($this->bag->getStatusCode());
    }

    public function test_all_returns_flat_array_of_header_strings(): void
    {
        $this->bag->set('Location', '/index.php');
        $this->bag->add('Set-Cookie', 'a=1');
        $this->bag->add('Set-Cookie', 'b=2');

        $all = $this->bag->all();

        $this->assertContains('Location: /index.php', $all);
        $this->assertContains('Set-Cookie: a=1', $all);
        $this->assertContains('Set-Cookie: b=2', $all);
    }

    public function test_to_response_headers_returns_associative_array(): void
    {
        $this->bag->set('Content-Type', 'image/png');
        $this->bag->add('Set-Cookie', 'a=1');
        $this->bag->add('Set-Cookie', 'b=2');

        $headers = $this->bag->toResponseHeaders();

        $this->assertSame('image/png', $headers['Content-Type']);
        $this->assertSame('a=1, b=2', $headers['Set-Cookie']);
    }

    public function test_to_response_headers_uses_conventional_casing(): void
    {
        $this->bag->set('content-type', 'text/html');
        $this->bag->set('location', '/redirect');

        $headers = $this->bag->toResponseHeaders();

        $this->assertArrayHasKey('Content-Type', $headers);
        $this->assertArrayHasKey('Location', $headers);
    }

    public function test_flush_clears_all_state(): void
    {
        $this->bag->set('Location', '/index.php');
        $this->bag->setStatusCode(302);

        $this->bag->flush();

        $this->assertNull($this->bag->first('Location'));
        $this->assertNull($this->bag->getStatusCode());
        $this->assertSame([], $this->bag->all());
        $this->assertSame([], $this->bag->toResponseHeaders());
    }

    public function test_flush_on_empty_bag_is_safe(): void
    {
        $this->bag->flush();

        $this->assertNull($this->bag->getStatusCode());
        $this->assertSame([], $this->bag->all());
    }

    public function test_first_returns_null_for_nonexistent_header(): void
    {
        $this->assertNull($this->bag->first('Nonexistent'));
    }

    public function test_get_returns_empty_array_for_nonexistent_header(): void
    {
        $this->assertSame([], $this->bag->get('Nonexistent'));
    }

    public function test_has_returns_false_for_nonexistent_header(): void
    {
        $this->assertFalse($this->bag->has('Nonexistent'));
    }
}
