<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\CollectionItem
 */
class CollectionItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->album->title,
            'cover_url' => $this->album->cover_url,
            'year' => $this->album->first_release_year,
            'artists' => $this->album->artists->map->only('id', 'name')->all(),
            'format' => $this->release?->format?->label(),
        ];
    }
}
