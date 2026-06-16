<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * A collection of transaction resources.
 */
class TransactionCollection extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        return [
            'data' => TransactionResource::collection($this->collection),
        ];
    }
}
