<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Traits\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CurrentUserController extends Controller
{
    use ApiResponse;

    /**
     * Return the currently authenticated user.
     */
    public function __invoke(Request $request): JsonResponse
    {
        return $this->resourceResponse(new UserResource($request->user()), 'User retrieved');
    }
}
