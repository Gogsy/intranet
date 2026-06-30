<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocNode extends Model
{
    use \App\Concerns\LogsModelActivity;

    protected $fillable = [
        'parent_id','title','slug','summary','description','brand_color','sort_order','is_active',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    // Hijerarhija
    public function parent(): BelongsTo
    {
        return $this->belongsTo(DocNode::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    // Aktivna djeca (podsekcije) poredana za prikaz
    public function activeChildren(): HasMany
    {
        return $this->children()->where('is_active', true)->orderBy('sort_order');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(\App\Models\DocAttachment::class, 'doc_node_id');
    }

    // Scope-ovi
    public function scopeActive($q)  { return $q->where('is_active', true); }
    public function scopeRoots($q)   { return $q->whereNull('parent_id'); }

    // Helper za breadcrumb title (fallback)
    public function getDisplayTitleAttribute(): string
    {
        return $this->title ?: strtoupper(str_replace('-', ' ', $this->slug));
    }

    /**
     * Ancestor chain (root first) — used to build breadcrumbs.
     */
    public function ancestors(): \Illuminate\Support\Collection
    {
        $chain = collect();
        $node = $this->parent;
        while ($node) {
            $chain->prepend($node);
            $node = $node->parent;
        }
        return $chain;
    }

    /**
     * All descendant ids (recursive) — used to prevent picking self/descendant
     * as a parent (which would create a cycle).
     */
    public function descendantIds(): array
    {
        $ids = [];
        foreach ($this->children as $child) {
            $ids[] = $child->id;
            $ids = array_merge($ids, $child->descendantIds());
        }
        return $ids;
    }
}
