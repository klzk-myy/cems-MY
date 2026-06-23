<?php

namespace App\Http\Resources\Api\V1\Compliance;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * A collection of compliance case resources.
 */
class CaseCollection extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        return [
            'data' => CaseResource::collection($this->collection),
        ];
    }
}
