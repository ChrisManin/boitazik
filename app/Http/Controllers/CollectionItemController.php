<?php

namespace App\Http\Controllers;

use App\Actions\AddAlbumToCollection;
use App\Actions\UpdateCollectionItem;
use App\Enums\AlbumType;
use App\Enums\ItemCondition;
use App\Enums\ReleaseFormat;
use App\Http\Requests\SearchBarcodeRequest;
use App\Http\Requests\SearchMusicBrainzRequest;
use App\Http\Requests\StoreCollectionItemRequest;
use App\Http\Requests\UpdateCollectionItemRequest;
use App\Http\Resources\CollectionItemResource;
use App\Http\Resources\EditableCollectionItemResource;
use App\Models\Album;
use App\Models\CollectionItem;
use App\Services\CoverArtArchive\CoverArtArchiveClient;
use App\Services\MusicBrainz\Data\ArtistCredit;
use App\Services\MusicBrainz\Data\Release as MusicBrainzRelease;
use App\Services\MusicBrainz\Data\ReleaseGroup;
use App\Services\MusicBrainz\MusicBrainzClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
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

        try {
            $addAlbum->handle($request->user(), $request->validated());
        } catch (RequestException|ConnectionException) {
            $error = $request->validated('release_mbid')
                ? ['release_mbid' => "Impossible d'importer cette édition depuis MusicBrainz. Réessayez ou cherchez l'album par titre."]
                : ['mbid' => "Impossible d'importer cet album depuis MusicBrainz. Réessayez ou saisissez-le à la main."];

            return back()->withErrors($error)->withInput();
        }

        return redirect()->route('collection.index')->with('success', 'Album ajouté à votre collection.');
    }

    // Release groups matching the search, for the create form
    public function search(SearchMusicBrainzRequest $request, MusicBrainzClient $musicBrainz, CoverArtArchiveClient $coverArt): JsonResponse
    {
        $this->authorize('create', CollectionItem::class);

        try {
            $releaseGroups = $musicBrainz->searchReleaseGroups($request->validated('title'), $request->validated('artist'));
        } catch (RequestException|ConnectionException) {
            return $this->musicBrainzUnavailable();
        }

        return response()->json([
            'data' => array_map(fn (ReleaseGroup $releaseGroup) => $this->albumResult($releaseGroup, $coverArt), $releaseGroups),
        ]);
    }

    // Editions printed with the barcode, for the create form
    public function barcode(SearchBarcodeRequest $request, MusicBrainzClient $musicBrainz, CoverArtArchiveClient $coverArt): JsonResponse
    {
        $this->authorize('create', CollectionItem::class);

        try {
            $releases = $musicBrainz->findReleasesByBarcode($request->validated('code'));
        } catch (RequestException|ConnectionException) {
            return $this->musicBrainzUnavailable();
        }

        return response()->json([
            'data' => array_map(fn (MusicBrainzRelease $release) => [
                'release_mbid' => $release->mbid,
                'album' => $this->albumResult($release->releaseGroup, $coverArt),
                'format' => ReleaseFormat::fromMusicBrainz($release->format)->label(),
                'label' => $release->label,
                'catalog_number' => $release->catalogNumber,
                'country' => $release->country,
                'year' => $release->releaseYear,
                'barcode' => $release->barcode,
            ], $releases),
        ]);
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
     * @return array{mbid: string, title: string, artists: list<string>, year: ?int, type: string, cover_url: string}
     */
    private function albumResult(ReleaseGroup $releaseGroup, CoverArtArchiveClient $coverArt): array
    {
        return [
            'mbid' => $releaseGroup->mbid,
            'title' => $releaseGroup->title,
            'artists' => array_map(fn (ArtistCredit $credit) => $credit->name, $releaseGroup->artists),
            'year' => $releaseGroup->firstReleaseYear,
            'type' => AlbumType::fromMusicBrainz($releaseGroup->primaryType, $releaseGroup->secondaryTypes)->label(),
            // The front shows a fallback when the image does not load
            'cover_url' => $coverArt->guessFrontCoverUrl($releaseGroup->mbid),
        ];
    }

    private function musicBrainzUnavailable(): JsonResponse
    {
        return response()->json(
            ['message' => "MusicBrainz ne répond pas pour le moment. Réessayez ou saisissez l'album à la main."],
            503,
        );
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
