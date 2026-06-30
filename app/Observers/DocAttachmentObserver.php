<?php

namespace App\Observers;

use App\Models\DocAttachment;
use App\Support\FileCleanup;

class DocAttachmentObserver
{
    public function deleting(DocAttachment $attachment): void
    {
        FileCleanup::deleteIfUnreferenced(
            $attachment->file_path,
            DocAttachment::class,
            'file_path',
            $attachment->getKey()
        );
    }
}
