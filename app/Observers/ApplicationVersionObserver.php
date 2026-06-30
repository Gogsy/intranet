<?php

namespace App\Observers;

use App\Models\ApplicationVersion;
use App\Support\FileCleanup;
use Illuminate\Support\Facades\Storage;

class ApplicationVersionObserver
{
    /** Fill in the file size from the stored APK when it isn't set. */
    public function saving(ApplicationVersion $version): void
    {
        if ((int) $version->size === 0 && $version->file_path
            && Storage::disk('public')->exists($version->file_path)) {
            $version->size = Storage::disk('public')->size($version->file_path);
        }
    }

    /** First version for an app becomes the active one automatically. */
    public function created(ApplicationVersion $version): void
    {
        $hasActive = ApplicationVersion::where('application_id', $version->application_id)
            ->where('id', '!=', $version->id)
            ->where('is_active', true)
            ->exists();

        if (! $hasActive) {
            $version->activate();
        }
    }

    public function deleting(ApplicationVersion $version): void
    {
        FileCleanup::deleteIfUnreferenced(
            $version->file_path, ApplicationVersion::class, 'file_path', $version->getKey()
        );
    }
}
