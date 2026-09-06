<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Responses;

use App\Http\Responses\ProblemDetails;
use Tests\TestCase;

final class ProblemDetailsTest extends TestCase
{
    public function test_validation_factory_creates_correct_structure(): void
    {
        $problem = ProblemDetails::validation(
            ['name' => ['The name field is required.']],
            '/api/v1/medals',
        );

        $array = $problem->toArray();

        $this->assertSame(422, $array['status']);
        $this->assertSame('Validation Error', $array['title']);
        $this->assertSame('The name field is required.', $array['detail']);
        $this->assertSame('/api/v1/medals', $array['instance']);
        $this->assertArrayHasKey('errors', $array);
        $this->assertSame(['name' => ['The name field is required.']], $array['errors']);
    }

    public function test_unauthorized_factory(): void
    {
        $problem = ProblemDetails::unauthorized('Token expired', '/api/v1/users');

        $array = $problem->toArray();

        $this->assertSame(401, $array['status']);
        $this->assertSame('Unauthorized', $array['title']);
        $this->assertSame('Token expired', $array['detail']);
    }

    public function test_forbidden_factory(): void
    {
        $problem = ProblemDetails::forbidden('Insufficient permissions', '/api/v1/users/1');

        $array = $problem->toArray();

        $this->assertSame(403, $array['status']);
        $this->assertSame('Forbidden', $array['title']);
    }

    public function test_not_found_factory(): void
    {
        $problem = ProblemDetails::notFound('User not found', '/api/v1/users/999');

        $array = $problem->toArray();

        $this->assertSame(404, $array['status']);
        $this->assertSame('Not Found', $array['title']);
    }

    public function test_server_error_factory(): void
    {
        $problem = ProblemDetails::serverError('Database connection failed', '/api/v1/medals');

        $array = $problem->toArray();

        $this->assertSame(500, $array['status']);
        $this->assertSame('Internal Server Error', $array['title']);
    }

    public function test_to_response_sets_content_type_and_status(): void
    {
        $problem = ProblemDetails::forbidden('No access', '/api/v1/admin');
        $response = $problem->toResponse();

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('application/problem+json', $response->headers->get('Content-Type'));
    }

    public function test_instance_is_omitted_when_null(): void
    {
        $problem = new ProblemDetails(
            status: 400,
            title: 'Bad Request',
            detail: 'Invalid input',
        );

        $array = $problem->toArray();

        $this->assertArrayNotHasKey('instance', $array);
    }

    public function test_extensions_are_merged_into_array(): void
    {
        $problem = new ProblemDetails(
            status: 422,
            title: 'Validation Error',
            detail: 'Multiple errors',
            extensions: ['errors' => ['field1' => ['error1']], 'trace' => 'abc'],
        );

        $array = $problem->toArray();

        $this->assertSame(['field1' => ['error1']], $array['errors']);
        $this->assertSame('abc', $array['trace']);
    }
}
