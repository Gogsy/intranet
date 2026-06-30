<?php

namespace App\Support;

use App\Models\Application;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class NesyVersionFetcher
{
    public const ENDPOINT = 'https://nesy-mobile-api.overseas.hr/Version/GetLatestVersion';

    /**
     * The API endpoint to use for this app — a per-app override (so another
     * company can point at its own server) falling back to the default.
     */
    public static function endpointFor(Application $app): string
    {
        return filled($app->update_endpoint) ? $app->update_endpoint : self::ENDPOINT;
    }

    /**
     * Ask the API for the latest build metadata. Returns the decoded `payload`
     * array (with versionNumber/downloadUrl) or null on any failure.
     */
    public function latestPayload(Application $app): ?array
    {
        if ($app->update_provider !== 'nesy' || blank($app->update_app_name)) {
            return null;
        }

        try {
            // Short timeouts (well under PHP's max_execution_time) so a slow or
            // unreachable endpoint fails cleanly instead of stalling the request
            // until PHP fatals — this also runs on the PUBLIC live-download route.
            $resp = Http::acceptJson()
                ->connectTimeout(5)
                ->timeout(12)
                ->post(self::endpointFor($app), ['AppName' => $app->update_app_name]);
        } catch (\Throwable $e) {
            return null;
        }

        return $resp->ok() ? ($resp->json('payload') ?? []) : null;
    }

    /**
     * The freshest download URL for this app, fetched live from the API.
     * Used by the public "live download" mode to redirect straight to the APK.
     */
    public function latestDownloadUrl(Application $app): ?string
    {
        $payload = $this->latestPayload($app);

        return $payload['downloadUrl'] ?? null;
    }

    /**
     * Fetch the latest version from the Nesy API, download the APK into the
     * application's version channel, and return a result array:
     *   ['ok' => bool, 'created' => bool, 'message' => string, 'version' => ?ApplicationVersion]
     */
    public function fetchLatest(Application $app): array
    {
        if ($app->update_provider !== 'nesy' || blank($app->update_app_name)) {
            return ['ok' => false, 'created' => false, 'message' => 'This app is not configured for Nesy updates.', 'version' => null];
        }

        // 1) Ask the API for the latest version metadata.
        $payload = $this->latestPayload($app);

        if ($payload === null) {
            return ['ok' => false, 'created' => false, 'message' => 'API request failed or returned an error.', 'version' => null];
        }

        $versionNumber = $payload['versionNumber'] ?? null;
        $downloadUrl = $payload['downloadUrl'] ?? null;

        if (empty($downloadUrl)) {
            return ['ok' => false, 'created' => false, 'message' => 'No downloadUrl in API response.', 'version' => null];
        }

        // 2) Skip if this version is already in the channel.
        if ($versionNumber !== null) {
            $existing = $app->versions()->where('version_number', (string) $versionNumber)->first();
            if ($existing) {
                return ['ok' => true, 'created' => false, 'message' => "Version {$versionNumber} is already in the channel.", 'version' => $existing];
            }
        }

        // 3) Download the APK (Azure blob, valid TLS) into local storage.
        // The build can be large and this runs from an explicit admin click, so
        // lift PHP's execution-time cap (the 30s default would kill mid-download).
        @set_time_limit(0);
        try {
            $dl = Http::connectTimeout(10)->timeout(180)->get($downloadUrl);
        } catch (\Throwable $e) {
            return ['ok' => false, 'created' => false, 'message' => 'APK download failed: ' . $e->getMessage(), 'version' => null];
        }

        if (! $dl->ok()) {
            return ['ok' => false, 'created' => false, 'message' => 'APK download returned HTTP ' . $dl->status(), 'version' => null];
        }

        $body = $dl->body();
        if (strlen($body) < 1024) {
            return ['ok' => false, 'created' => false, 'message' => 'Downloaded file looks too small to be an APK.', 'version' => null];
        }

        $slug = Str::slug($app->update_app_name);
        $label = (string) ($versionNumber ?? now()->format('YmdHis'));

        // Keep the original APK filename (e.g. app-260-Nesy-Mobile-Prod-….apk)
        // so it is both the version's name and the downloaded file's name.
        $originalName = basename(strtok($downloadUrl, '?'));
        if ($originalName === '' || ! str_ends_with(strtolower($originalName), '.apk')) {
            $originalName = "{$slug}-v{$label}.apk";
        }

        $path = "apps/{$slug}/{$originalName}";
        Storage::disk('public')->put($path, $body);

        // 4) Record the version. The observer auto-activates it only if the app
        //    has no active version yet (first one); otherwise it stays inactive.
        $isFirst = ! $app->activeVersion()->exists();
        $version = $app->versions()->create([
            'version_number' => $label,
            'file_path'      => $path,
            'source'         => 'api',
            'size'           => strlen($body),
            'original_url'   => strtok($downloadUrl, '?'), // drop SAS query
        ]);

        $note = $isFirst ? ' (set active — first version)' : ' (inactive — activate it when ready)';

        return [
            'ok' => true,
            'created' => true,
            'message' => "Fetched version {$label}, {$version->size_for_humans}{$note}.",
            'version' => $version,
        ];
    }
}
