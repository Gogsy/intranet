<?php

namespace App\Observers;

use App\Models\DocNode;
use Illuminate\Support\Str;

class DocNodeObserver
{
    /**
     * Auto-generate a unique slug from the title. Runs only when the slug is
     * empty (i.e. on create), so renaming a section later keeps existing URLs.
     */
    public function saving(DocNode $node): void
    {
        if (filled($node->slug)) {
            return;
        }

        $base = Str::slug((string) $node->title) ?: 'section';
        $slug = $base;
        $i = 2;
        while (DocNode::where('slug', $slug)->where('id', '!=', $node->id)->exists()) {
            $slug = $base . '-' . $i++;
        }
        $node->slug = $slug;
    }

    public function deleting(DocNode $node): void
    {
        // Cascade delete sub-sections recursively. Deleting each child via
        // Eloquent fires its own observer, so the whole subtree's documents
        // (and their files) get cleaned up — and avoids the DB nullOnDelete
        // that would otherwise orphan children to the top level.
        foreach ($node->children as $child) {
            $child->delete();
        }

        // Delete this section's documents (files removed via DocAttachmentObserver).
        foreach ($node->attachments as $attachment) {
            $attachment->delete();
        }
    }
}
