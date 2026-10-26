<?php

namespace App\Models;

use App\Jobs\ImportArtistDiscography;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Artist extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'sort_name',
        'mbid',
        'spotify_id',
        'country',
        'image_url',
        'enriched_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'enriched_at' => 'datetime',
    ];

    // The discography is fetched again from MusicBrainz after this many days
    public const DISCOGRAPHY_REFRESH_DAYS = 30;

    protected static function booted(): void
    {
        // After commit: the job must not run before the artist exists for the queue worker
        static::created(fn (Artist $artist) => $artist->queueDiscographyImport());
        // A new MBID is another discography: it is imported even if the old one is fresh
        static::updated(function (Artist $artist) {
            if ($artist->wasChanged('mbid')) {
                $artist->queueDiscographyImport(force: true);
            }
        });
    }

    private function queueDiscographyImport(bool $force = false): void
    {
        if ($this->mbid !== null && ($force || $this->needsDiscographyImport())) {
            ImportArtistDiscography::dispatch($this)->afterCommit();
        }
    }

    public function needsDiscographyImport(): bool
    {
        return $this->enriched_at === null
            || $this->enriched_at->lt(now()->subDays(self::DISCOGRAPHY_REFRESH_DAYS));
    }

    public function albums(): BelongsToMany
    {
        return $this->belongsToMany(Album::class)
            ->withPivot('position')
            ->orderByPivot('position');
    }
}
