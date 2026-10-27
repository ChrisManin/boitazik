<?php

namespace App\Http\Controllers;

use App\Enums\AlbumType;
use App\Models\Album;
use App\Models\Artist;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ArtistController extends Controller
{
    // Order of the discography sections; empty ones are not shown
    private const TYPE_ORDER = [
        AlbumType::Album,
        AlbumType::Ep,
        AlbumType::Live,
        AlbumType::Compilation,
        AlbumType::Soundtrack,
        AlbumType::Single,
        AlbumType::Other,
    ];

    // The catalog is shared: any authenticated user can see any artist
    public function show(Request $request, Artist $artist): Response
    {
        $albums = $artist->albums()
            ->withExists(['collectionItems as owned' => fn ($query) => $query->where('user_id', $request->user()->id)])
            ->reorder()
            // Unknown years last
            ->orderByRaw('first_release_year IS NULL')
            ->orderBy('first_release_year')
            ->orderBy('title')
            ->get()
            ->groupBy(fn (Album $album) => $album->type->value);

        // A type missing from TYPE_ORDER still shows, at the end
        $order = [...self::TYPE_ORDER, ...array_filter(AlbumType::cases(), fn (AlbumType $type) => ! in_array($type, self::TYPE_ORDER, true))];

        $discography = [];
        foreach ($order as $type) {
            if ($albums->has($type->value)) {
                $discography[] = [
                    'type' => $type->value,
                    'label' => $type->label(),
                    'albums' => $albums[$type->value]->map(fn (Album $album) => [
                        'id' => $album->id,
                        'title' => $album->title,
                        'year' => $album->first_release_year,
                        'cover_url' => $album->cover_url,
                        'owned' => (bool) $album->owned,
                    ])->all(),
                ];
            }
        }

        return Inertia::render('Artists/Show', [
            'artist' => $artist->only('id', 'name', 'country', 'image_url'),
            // Without an MBID the discography is never imported
            'importing' => $artist->mbid !== null && $artist->enriched_at === null && $artist->discography_failed_at === null,
            // A failed refresh is not shown: the previous discography is still there
            'importFailed' => $artist->enriched_at === null && $artist->discography_failed_at !== null,
            'discography' => $discography,
        ]);
    }
}
