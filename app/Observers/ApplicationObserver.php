<?php

namespace App\Observers;

use App\Models\Application;
use App\Support\FileCleanup;

class ApplicationObserver
{
    public function deleting(Application $application): void
    {
        $id = $application->getKey();

        // Delete version APKs (FK cascade would bypass the version observer/files).
        foreach ($application->versions as $version) {
            $version->delete();
        }

        FileCleanup::deleteIfUnreferenced($application->link, Application::class, 'link', $id);
        FileCleanup::deleteIfUnreferenced($application->pdf_installation_instructions, Application::class, 'pdf_installation_instructions', $id);
        FileCleanup::deleteIfUnreferenced($application->pdf_user_manual, Application::class, 'pdf_user_manual', $id);

        // icon: new uploads store a full storage path; legacy bare filenames point
        // to the committed library (protected) or a storage sub-folder.
        $icon = $application->icon;
        if (! empty($icon)) {
            if (str_contains($icon, '/')) {
                FileCleanup::deleteIfUnreferenced($icon, Application::class, 'icon', $id);
            } else {
                FileCleanup::deleteUploadedIconIfUnreferenced(
                    $icon, Application::class, 'images/icons', ['images/icons'], $id
                );
            }
        }
    }
}
