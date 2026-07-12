<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

trait AuthorizesBranchResource
{
    protected function authorizeBranchResource(
        Model $resource,
        string $action = 'access',
        ?string $message = null
    ): true|JsonResponse {
        $user = Auth::user();

        if ($user === null) {
            return $this->denyResponse('Unauthenticated.', 401);
        }

        if ($user->isAdmin()) {
            return true;
        }

        $resourceBranchId = $resource instanceof Branch
            ? $resource->getKey()
            : $resource->getAttribute('branch_id');

        if ($resourceBranchId !== null && (int) $resourceBranchId !== (int) $user->branch_id) {
            return $this->denyResponse(
                $message ?? "You can only {$action} resources for your own branch.",
                403
            );
        }

        return true;
    }

    private function denyResponse(string $message, int $status): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => [],
        ], $status);
    }
}
