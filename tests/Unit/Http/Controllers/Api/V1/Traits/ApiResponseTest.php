<?php

namespace Tests\Unit\Http\Controllers\Api\V1\Traits;

use App\Http\Controllers\Api\V1\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ApiResponseTest extends TestCase
{
    use ApiResponse;

    public function test_success_response_returns_expected_shape(): void
    {
        $response = $this->successResponse(['id' => 1], 'Created');

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals([
            'success' => true,
            'message' => 'Created',
            'data' => ['id' => 1],
        ], $response->getData(true));
    }

    public function test_error_response_returns_expected_shape(): void
    {
        $response = $this->errorResponse('Validation failed', ['field' => ['required']], 422);

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertEquals([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => ['field' => ['required']],
        ], $response->getData(true));
    }

    public function test_resource_response_wraps_with_success(): void
    {
        $resource = new JsonResource(['id' => 1]);
        $response = $this->resourceResponse($resource, 'OK');

        $this->assertEquals([
            'success' => true,
            'message' => 'OK',
            'data' => ['id' => 1],
        ], $response->getData(true));
    }

    public function test_resource_response_can_return_custom_status_code(): void
    {
        $resource = new JsonResource(['id' => 1]);
        $response = $this->resourceResponse($resource, 'Created', 201);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals([
            'success' => true,
            'message' => 'Created',
            'data' => ['id' => 1],
        ], $response->getData(true));
    }

    public function test_not_found_response_returns_expected_shape(): void
    {
        $response = $this->notFoundResponse('Not found.');

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals([
            'success' => false,
            'message' => 'Not found.',
            'errors' => [],
        ], $response->getData(true));
    }

    public function test_server_error_response_logs_exception_when_provided(): void
    {
        Log::spy();
        $exception = new \Exception('Something went wrong');

        $response = $this->serverErrorResponse('Server error.', $exception);

        $this->assertEquals(500, $response->getStatusCode());
        $this->assertEquals([
            'success' => false,
            'message' => 'Server error.',
            'errors' => [],
        ], $response->getData(true));
        Log::shouldHaveReceived('error')->once()->with('Server error.', [
            'exception' => 'Something went wrong',
            'trace' => $exception->getTraceAsString(),
        ]);
    }

    public function test_server_error_response_does_not_log_without_exception(): void
    {
        Log::spy();

        $response = $this->serverErrorResponse('Server error.');

        $this->assertEquals(500, $response->getStatusCode());
        $this->assertEquals([
            'success' => false,
            'message' => 'Server error.',
            'errors' => [],
        ], $response->getData(true));
        Log::shouldNotHaveReceived('error');
    }
}
