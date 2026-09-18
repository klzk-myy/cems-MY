<?php

namespace App\Http\Controllers\Api\V1\Traits;

use App\Exceptions\Domain\DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Log;

trait ApiResponse
{
    /**
     * @param  array<string, mixed>  $meta
     */
    protected function successResponse(
        mixed $data = null,
        string $message = 'Success',
        int $code = 200,
        array $meta = []
    ): JsonResponse {
        return response()->json(array_merge([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $meta), $code);
    }

    /**
     * @param  array<string, mixed>  $errors
     * @param  array<string, mixed>  $meta
     */
    protected function errorResponse(
        string $message,
        array $errors = [],
        int $code = 422,
        array $meta = []
    ): JsonResponse {
        return response()->json(array_merge([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $meta), $code);
    }

    /**
     * Standard error response for domain exceptions: the exception's own
     * declared status and stable machine-readable code. Controllers may
     * override the message with friendlier copy without losing the code.
     *
     * @param  array<string, mixed>  $errors
     */
    protected function domainErrorResponse(
        DomainException $e,
        ?string $message = null,
        array $errors = []
    ): JsonResponse {
        return $this->errorResponse(
            $message ?? $e->getMessage(),
            array_merge(['code' => $e->getErrorCode()], $errors),
            $e->getStatusCode()
        );
    }

    protected function resourceResponse(
        JsonResource $resource,
        string $message = 'Success',
        int $code = 200
    ): JsonResponse {
        return $resource->additional([
            'success' => true,
            'message' => $message,
        ])->response()->setStatusCode($code);
    }

    /**
     * @template TResource of JsonResource
     *
     * @param  TResource  $resource
     * @param  array<string, mixed>  $meta
     * @return TResource
     */
    protected function resourceWithSuccess(
        JsonResource $resource,
        string $message = 'Success',
        array $meta = []
    ): JsonResource {
        return $resource->additional(array_merge([
            'success' => true,
            'message' => $message,
        ], $meta));
    }

    protected function notFoundResponse(string $message = 'Resource not found.'): JsonResponse
    {
        return $this->errorResponse($message, [], 404);
    }

    protected function serverErrorResponse(
        string $message = 'An error occurred.',
        ?\Throwable $e = null
    ): JsonResponse {
        if ($e !== null) {
            Log::error($message, ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
        }

        return $this->errorResponse($message, [], 500);
    }
}
