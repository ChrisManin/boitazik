<?php

namespace App\Jobs;

use App\Enums\AlbumType;
use App\Models\Album;
use App\Models\Artist;
use App\Services\CoverArtArchive\CoverArtArchiveClient;
use App\Services\MusicBrainz\Data\ArtistCredit;
use App\Services\MusicBrainz\Data\ReleaseGroup;
use App\Services\MusicBrainz\MusicBrainzClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

// Brings every album of an artist into the shared catalog; the MusicBrainz client keeps to its rate limit
class ImportArtistDiscography implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    // An artist deleted or merged in the back office before the job runs: nothing left to import
    public bool $deleteWhenMissingModels = true;

    private const PRIMARY_TYPES = ['Album', 'EP'];

    // Release groups that are not music, whatever their primary type
    private const NON_MUSIC_TYPES = ['Interview', 'Spokenword', 'Audiobook', 'Audio drama'];

    public function __construct(public Artist $artist) {}

    public function uniqueId(): string
    {
        return (string) $this->artist->id;
    }

    /**
     * @return array<int, int> seconds to wait before each new attempt
     */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(MusicBrainzClient $musicBrainz, CoverArtArchiveClient $coverArt): void
    {
        foreach ($musicBrainz->browseArtistReleaseGroups($this->artist->mbid) as $releaseGroup) {
            if ($this->belongsInDiscography($releaseGroup)) {
                $this->importAlbum($releaseGroup, $coverArt);
            }
        }

        $this->artist->update(['enriched_at' => now()]);
    }

    // Albums and EPs, live or compilation included; singles and broadcasts are left out
    private function belongsInDiscography(ReleaseGroup $releaseGroup): bool
    {
        return in_array($releaseGroup->primaryType, self::PRIMARY_TYPES, true)
            && array_intersect($releaseGroup->secondaryTypes, self::NON_MUSIC_TYPES) === [];
    }

    private function importAlbum(ReleaseGroup $releaseGroup, CoverArtArchiveClient $coverArt): void
    {
        $album = Album::where('mbid', $releaseGroup->mbid)->first() ?? $this->findManualAlbum($releaseGroup->title);

        // HTTP calls stay outside the transaction: no lock is held while waiting for the APIs
        $coverUrl = $album?->cover_url ?? $this->coverUrl($coverArt, $releaseGroup->mbid);

        DB::transaction(function () use ($album, $releaseGroup, $coverUrl) {
            if ($album === null) {
                $album = Album::create([
                    'mbid' => $releaseGroup->mbid,
                    'title' => $releaseGroup->title,
                    'type' => AlbumType::fromMusicBrainz($releaseGroup->primaryType, $releaseGroup->secondaryTypes),
                    'first_release_year' => $releaseGroup->firstReleaseYear,
                    'cover_url' => $coverUrl,
                ]);
            } else {
                // A known album keeps what it has: only the gaps are filled
                $album->update([
                    'mbid' => $releaseGroup->mbid,
                    'first_release_year' => $album->first_release_year ?? $releaseGroup->firstReleaseYear,
                    'cover_url' => $coverUrl,
                ]);
            }

            if (! $this->artist->albums()->whereKey($album->id)->exists()) {
                $this->artist->albums()->attach($album, ['position' => $this->creditPosition($releaseGroup, $album)]);
            }
        });
    }

    // An album entered by hand: no MBID, same title, credited to this artist
    private function findManualAlbum(string $title): ?Album
    {
        return $this->artist->albums()
            ->whereNull('albums.mbid')
            ->whereRaw('LOWER(albums.title) = ?', [mb_strtolower($title)])
            ->orderBy('albums.id')
            ->first();
    }

    // Other credited artists are not created: each would queue its own discography, endlessly
    private function creditPosition(ReleaseGroup $releaseGroup, Album $album): int
    {
        $mbids = array_map(fn (ArtistCredit $credit) => $credit->mbid, $releaseGroup->artists);
        $position = array_search($this->artist->mbid, $mbids, true);

        return $position !== false ? $position : $album->artists()->count();
    }

    // A missing cover never blocks the import
    private function coverUrl(CoverArtArchiveClient $coverArt, string $mbid): ?string
    {
        try {
            return $coverArt->frontCoverUrl($mbid);
        } catch (RequestException|ConnectionException) {
            return null;
        }
    }
}
