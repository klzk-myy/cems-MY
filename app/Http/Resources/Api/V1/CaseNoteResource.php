<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * Transform a compliance case note into a JSON resource.
 *
 * @property int $id
 * @property int $case_id
 * @property int $author_id
 * @property string|null $note_type
 * @property string $content
 * @property bool $is_internal
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CaseNoteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'case_id' => $this->case_id,
            'author_id' => $this->author_id,
            'note_type' => $this->note_type,
            'content' => $this->content,
            'is_internal' => $this->is_internal,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'author' => new UserResource($this->whenLoaded('author')),
        ];
    }
}
