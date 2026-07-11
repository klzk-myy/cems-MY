<?php

namespace App\Http\Controllers\Api\V1\Concerns;

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
            return $this->errorResponse('Unauthenticated.', [], 401);
        }

        if ($user->isAdmin()) {
            return true;
        }

        $resourceBranchId = $resource instanceof Branch
            ? $resource->getKey()
            : $resource->getAttribute('branch_id');

        if ($resourceBranchId !== null && $resourceBranchId !== $user->branch_id) {
            return $this->errorResponse(
                $message ?? "You can only {$action} resources for your own branch.",
                [],
                403
            );
        }

        return true;
    }
}
