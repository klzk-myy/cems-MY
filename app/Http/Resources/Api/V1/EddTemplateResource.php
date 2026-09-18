<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * Transform an EDD questionnaire template into a JSON resource.
 *
 * @property int $id
 * @property string $name
 * @property string|null $version
 * @property bool $is_active
 * @property array<int, mixed>|null $questions
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class EddTemplateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'version' => $this->version,
            'is_active' => $this->is_active,
            'questions' => $this->questions,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
