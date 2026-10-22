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
        $path = "release-group/{$releaseGroupMbid}/front-{$size}";

        $response = $this->request()->head($path);

        if ($response->notFound()) {
            return null;
        }

        $response->throw();

        return rtrim(config('services.coverartarchive.base_url'), '/').'/'.$path;
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
