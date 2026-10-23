<?php

namespace App\Services\CoverArtArchive;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Throwable;

final class CoverArtArchiveClient
{
    /**
     * Stable URL of the release group's front cover, or null when it has none.
     *
     * Cover Art Archive answers a redirect to the image when it exists and a 404 otherwise,
     * so a HEAD request without following the redirect is enough.
     */
    public function frontCoverUrl(string $releaseGroupMbid, int $size = 250): ?string
    {
        $response = $this->request()->head($this->frontCoverPath($releaseGroupMbid, $size));

        if ($response->notFound()) {
            return null;
        }

        $response->throw();

        return $this->guessFrontCoverUrl($releaseGroupMbid, $size);
    }

    /**
     * Same URL as frontCoverUrl(), built without any call: the cover may not exist.
     */
    public function guessFrontCoverUrl(string $releaseGroupMbid, int $size = 250): string
    {
        return rtrim(config('services.coverartarchive.base_url'), '/').'/'.$this->frontCoverPath($releaseGroupMbid, $size);
    }

    private function frontCoverPath(string $releaseGroupMbid, int $size): string
    {
        return "release-group/{$releaseGroupMbid}/front-{$size}";
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(config('services.coverartarchive.base_url'))
            ->timeout(config('services.coverartarchive.timeout'))
            ->retry(3, 500, $this->shouldRetry(...), throw: false)
            ->withoutRedirecting()
            ->withUserAgent(config('services.coverartarchive.user_agent'));
    }

    /**
     * Retry only transient failures: a redirect (cover found) or a 404 (no cover) is a final answer.
     */
    private function shouldRetry(?Throwable $e): bool
    {
        return $e instanceof ConnectionException
            || ($e instanceof RequestException && $e->response->serverError());
    }
}
