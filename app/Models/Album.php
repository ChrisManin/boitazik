<?php

namespace App\Models;

use App\Enums\AlbumType;
use App\Enums\ReleaseFormat;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Album extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'title',
        'type',
        'first_release_year',
        'mbid',
        'spotify_id',
        'cover_url',
        'enriched_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'type' => AlbumType::class,
        'first_release_year' => 'integer',
        'enriched_at' => 'datetime',
    ];

    public function artists(): BelongsToMany
    {
        return $this->belongsToMany(Artist::class)
            ->withPivot('position')
            ->orderByPivot('position');
    }

    public function releases(): HasMany
    {
        return $this->hasMany(Release::class);
    }

    // Only a release with no detail is reused: a detailed one is a specific pressing
    public function minimalRelease(ReleaseFormat $format): Release
    {
        return $this->releases()->firstOrCreate([
            'format' => $format,
            'label' => null,
            'catalog_number' => null,
            'country' => null,
            'release_year' => null,
            'barcode' => null,
            'mbid' => null,
        ]);
    }
}
