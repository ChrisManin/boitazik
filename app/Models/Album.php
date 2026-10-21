<?php

namespace App\Models;

use App\Enums\AlbumType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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
}
