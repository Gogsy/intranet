<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class DocAttachment extends Model
{
    use \App\Concerns\LogsModelActivity;

    protected $fillable = [
        'doc_node_id','label','type','file_path','url','sort_order','is_active','notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function node(): BelongsTo
    {
        return $this->belongsTo(DocNode::class, 'doc_node_id');
    }

    // Public URL to use in views: external url or stored file path
    public function getHrefAttribute(): string
    {
        if (($this->type ?? null) === 'url' && !empty($this->url)) {
            return $this->url;
        }

        if (!empty($this->file_path)) {
            // assume stored on 'public' disk
            if (Storage::disk('public')->exists($this->file_path)) {
                return Storage::url($this->file_path);
            }

            return asset('storage/' . ltrim($this->file_path, '/'));
        }

        return '#';
    }

    /**
     * Is this attachment an image file (logo, picture)? Used to show thumbnails.
     */
    public function getIsImageAttribute(): bool
    {
        if (($this->type ?? null) !== 'file' || empty($this->file_path)) {
            return false;
        }

        return (bool) preg_match('/\.(png|jpe?g|svg|webp|gif)$/i', $this->file_path);
    }

    /**
     * Short kind label for the UI: link | image | pdf | file.
     */
    public function getKindAttribute(): string
    {
        if (($this->type ?? null) === 'url') {
            return 'link';
        }
        if ($this->is_image) {
            return 'image';
        }
        if (!empty($this->file_path) && preg_match('/\.pdf$/i', $this->file_path)) {
            return 'pdf';
        }
        return 'file';
    }
}
