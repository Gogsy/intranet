<?php

namespace App\Observers;

use App\Models\Tool;
use App\Support\FileCleanup;

class ToolObserver
{
    public function deleting(Tool $tool): void
    {
        $icon = $tool->icon;
        if (empty($icon)) {
            return;
        }

        if (str_contains($icon, '/')) {
            // New uploads store a full storage path in `icon`.
            FileCleanup::deleteIfUnreferenced($icon, Tool::class, 'icon', $tool->getKey());
            return;
        }

        // Legacy bare filename: committed library icons are protected; uploaded
        // copies live under images/icons/tool_icons and may be reused.
        FileCleanup::deleteUploadedIconIfUnreferenced(
            $icon, Tool::class, 'images/icons/tool_icons', ['images/icons/tool_icons'], $tool->getKey()
        );
    }
}
