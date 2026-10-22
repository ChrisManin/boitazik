<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/**
 * A copy as loaded in the edit form: enum values instead of labels.
 *
 * @mixin \App\Models\CollectionItem
 */
class EditableCollectionItemResource extends CollectionItemResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'format' => $this->release?->format?->value,
            'condition' => $this->condition?->value,
            'acquired_at' => $this->acquired_at?->toDateString(),
            'notes' => $this->notes,
        ];
    }
}
