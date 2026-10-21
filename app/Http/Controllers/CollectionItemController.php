<?php

namespace App\Http\Controllers;

use App\Actions\AddAlbumToCollection;
use App\Actions\UpdateCollectionItem;
use App\Enums\ItemCondition;
use App\Enums\ReleaseFormat;
use App\Http\Requests\StoreCollectionItemRequest;
use App\Http\Requests\UpdateCollectionItemRequest;
use App\Http\Resources\CollectionItemResource;
use App\Http\Resources\EditableCollectionItemResource;
use App\Models\Album;
use App\Models\CollectionItem;
use Illuminate\Http\RedirectResponse;
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

    public function create(): Response
    {
        $this->authorize('create', CollectionItem::class);

        return Inertia::render('Collection/Create', $this->formOptions());
    }

    public function store(StoreCollectionItemRequest $request, AddAlbumToCollection $addAlbum): RedirectResponse
    {
        $this->authorize('create', CollectionItem::class);

        $addAlbum->handle($request->user(), $request->validated());

        return redirect()->route('collection.index')->with('success', 'Album ajouté à votre collection.');
    }

    public function edit(CollectionItem $item): Response
    {
        $this->authorize('update', $item);

        $item->load('album.artists', 'release');

        return Inertia::render('Collection/Edit', [
            'item' => (new EditableCollectionItemResource($item))->resolve(),
            ...$this->formOptions(),
        ]);
    }

    public function update(UpdateCollectionItemRequest $request, CollectionItem $item, UpdateCollectionItem $updateItem): RedirectResponse
    {
        $this->authorize('update', $item);

        $updateItem->handle($item, $request->validated());

        return redirect()->route('collection.index')->with('success', 'Exemplaire modifié.');
    }

    // Only the copy is removed: the shared catalog stays
    public function destroy(CollectionItem $item): RedirectResponse
    {
        $this->authorize('delete', $item);

        $item->delete();

        return redirect()->route('collection.index')->with('success', 'Album retiré de votre collection.');
    }

    /**
     * @return array{formats: list<array{value: string, label: string}>, conditions: list<array{value: string, label: string}>}
     */
    private function formOptions(): array
    {
        return [
            'formats' => ReleaseFormat::options(),
            'conditions' => ItemCondition::options(),
        ];
    }
}
