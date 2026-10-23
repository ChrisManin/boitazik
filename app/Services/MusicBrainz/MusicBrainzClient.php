<?php

namespace App\Services\MusicBrainz;

use App\Services\MusicBrainz\Data\Release;
use App\Services\MusicBrainz\Data\ReleaseGroup;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Sleep;

final class MusicBrainzClient
{
    /**
     * @return array<int, ReleaseGroup>
     */
    public function searchReleaseGroups(string $title, ?string $artist = null, int $limit = 10): array
    {
        $query = 'releasegroup:'.$this->phrase($title);

        if ($artist !== null && $artist !== '') {
            $query .= ' AND artist:'.$this->phrase($artist);
        }

        $response = $this->request()
            ->get('release-group', ['query' => $query, 'limit' => $limit, 'fmt' => 'json'])
            ->throw();

        return array_map(
            fn (array $item) => ReleaseGroup::fromArray($item),
            $response->json('release-groups', []),
        );
    }

    public function lookupReleaseGroup(string $mbid): ReleaseGroup
    {
        $response = $this->request()
            ->get("release-group/{$mbid}", ['inc' => 'artist-credits', 'fmt' => 'json'])
            ->throw();

        return ReleaseGroup::fromArray($response->json());
    }

    /**
     * Every release group credited to the artist, all pages fetched.
     *
     * @return array<int, ReleaseGroup>
     */
    public function browseArtistReleaseGroups(string $artistMbid): array
    {
        $releaseGroups = [];

        do {
            $response = $this->request()
                ->get('release-group', [
                    'artist' => $artistMbid,
                    'inc' => 'artist-credits',
                    'limit' => 100,
                    'offset' => count($releaseGroups),
                    'fmt' => 'json',
                ])
                ->throw();

            $page = $response->json('release-groups', []);
            array_push($releaseGroups, ...array_map(fn (array $item) => ReleaseGroup::fromArray($item), $page));
        } while ($page !== [] && count($releaseGroups) < $response->json('release-group-count', 0));

        return $releaseGroups;
    }

    /**
     * Editions printed with this barcode, as an EAN-13 or a UPC-A.
     *
     * @return array<int, Release>
     */
    public function findReleasesByBarcode(string $barcode): array
    {
        $digits = preg_replace('/\D/', '', $barcode);

        if ($digits === '') {
            return [];
        }

        $response = $this->request()
            ->get('release', ['query' => 'barcode:'.$this->barcodeVariants($digits), 'limit' => 25, 'fmt' => 'json'])
            ->throw();

        return array_map(
            fn (array $item) => Release::fromArray($item),
            $response->json('releases', []),
        );
    }

    public function lookupRelease(string $mbid): Release
    {
        $response = $this->request()
            ->get("release/{$mbid}", ['inc' => 'labels release-groups artist-credits media', 'fmt' => 'json'])
            ->throw();

        return Release::fromArray($response->json());
    }

    // A UPC-A is an EAN-13 without its leading zero: MusicBrainz may hold either form
    private function barcodeVariants(string $digits): string
    {
        return match (true) {
            strlen($digits) === 12 => "({$digits} OR 0{$digits})",
            strlen($digits) === 13 && $digits[0] === '0' => "({$digits} OR ".substr($digits, 1).')',
            default => $digits,
        };
    }

    /**
     * Quote a value as a Lucene phrase, escaping the characters that would end it.
     */
    private function phrase(string $value): string
    {
        return '"'.addcslashes($value, '"\\').'"';
    }

    private function request(): PendingRequest
    {
        $this->throttle();

        return Http::baseUrl(config('services.musicbrainz.base_url'))
            ->timeout(config('services.musicbrainz.timeout'))
            ->retry(3, 500, throw: false)
            ->acceptJson()
            ->withUserAgent(config('services.musicbrainz.user_agent'));
    }

    private function throttle(): void
    {
        $perSecond = config('services.musicbrainz.requests_per_second');

        while (! RateLimiter::attempt('musicbrainz', $perSecond, fn () => true, 1)) {
            Sleep::for(100)->milliseconds();
        }
    }
}
