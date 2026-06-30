<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Support\NesyVersionFetcher;
use Illuminate\Support\Facades\Storage;

class AppsController extends Controller
{
    public function index()
    {
        $apps = Application::where('is_visible', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
        return view('app_index', compact('apps'));
    }

    /**
     * Stable, permanent download link for an app. Always streams the currently
     * active version (or the legacy single APK). The URL never changes even when
     * the active version is switched/rolled back — safe to bookmark or QR.
     */
    public function download(Application $application, NesyVersionFetcher $nesy)
    {
        // Live mode: ask the API for the latest build and redirect straight to it
        // (always-latest, nothing stored) — this is the nesyapk script behaviour.
        if ($application->isLiveDownload()) {
            $url = $nesy->latestDownloadUrl($application);
            abort_unless($url, 502, 'Could not fetch the latest version from the update server.');

            return redirect()->away($url);
        }

        $active = $application->activeVersion()->first();
        $path = $active?->file_path ?: $application->link;

        abort_unless($path && Storage::disk('public')->exists($path), 404, 'No downloadable version for this app.');

        // download() sets Content-Disposition with the file's real (versioned) name.
        return Storage::disk('public')->download($path);
    }
}
