<?php

namespace App\Services\MusicBrainz;

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
