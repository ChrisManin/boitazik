<?php

namespace App\Http\Controllers;

use App\Http\Resources\CollectionItemResource;
use App\Models\Album;
use App\Models\CollectionItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CollectionItemController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', CollectionItem::class);

        // Sort key of the album's main artist (lowest pivot position)
        $mainArtist = DB::table('album_artist')
            ->join('artists', 'artists.id', '=', 'album_artist.artist_id')
            ->whereColumn('album_artist.album_id', 'collection_items.album_id')
            ->orderBy('album_artist.position')
            ->limit(1)
            ->selectRaw('COALESCE(artists.sort_name, artists.name)');

        $albumTitle = Album::select('title')->whereColumn('albums.id', 'collection_items.album_id');

        $items = $request->user()->collectionItems()
            ->with('album.artists', 'release')
            ->orderBy($mainArtist)
            ->orderBy($albumTitle)
            ->orderBy('id')
            ->paginate(24);

        return Inertia::render('Collection/Index', [
            'items' => CollectionItemResource::collection($items),
        ]);
    }
}
