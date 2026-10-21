<?php

namespace App\Actions;

use App\Enums\ReleaseFormat;
use App\Models\CollectionItem;
use App\Models\Release;
use Illuminate\Support\Facades\DB;

// Updates the user's own fields on a copy; the shared catalog is never changed
class UpdateCollectionItem
{
    /**
     * @param  array{format?: ?string, condition?: ?string, acquired_at?: ?string, notes?: ?string}  $data
     */
    public function handle(CollectionItem $item, array $data): CollectionItem
    {
        return DB::transaction(function () use ($item, $data) {
            $format = isset($data['format']) ? ReleaseFormat::from($data['format']) : null;

            $item->update([
                'release_id' => $this->releaseFor($item, $format)?->id,
                'condition' => $data['condition'] ?? null,
                'acquired_at' => $data['acquired_at'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            return $item;
        });
    }

    // Keep the current release, even a detailed one, while its format is unchanged
    private function releaseFor(CollectionItem $item, ?ReleaseFormat $format): ?Release
    {
        if ($format === null) {
            return null;
        }

        if ($item->release?->format === $format) {
            return $item->release;
        }

        return $item->album->minimalRelease($format);
    }
}
