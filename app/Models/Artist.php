<?php

namespace App\Models;

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

    public function albums(): BelongsToMany
    {
        return $this->belongsToMany(Album::class)
            ->withPivot('position')
            ->orderByPivot('position');
    }
}
