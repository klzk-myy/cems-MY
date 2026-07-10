<?php

namespace App\Http\Controllers\Api\V1\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;

trait ApiResponse
{
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

    protected function errorResponse(
        string $message,
        array $errors = [],
        int $code = 400,
        array $meta = []
    ): JsonResponse {
        return response()->json(array_merge([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $meta), $code);
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
            \Log::error($message, ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
        }

        return $this->errorResponse($message, [], 500);
    }
}
