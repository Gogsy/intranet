<?php

namespace App\Models;

use App\Concerns\LogsModelActivity;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocNode extends Model
{
    use LogsModelActivity;

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
        return $this->hasMany(DocAttachment::class, 'doc_node_id');
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
    public function ancestors(): Collection
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

    /** Nesting level — 0 for a top-level (root) node. Used to indent the admin table. */
    public function getDepthAttribute(): int
    {
        return $this->ancestors()->count();
    }

    /**
     * All node ids in depth-first tree order (each parent immediately
     * followed by its children, siblings ordered by sort_order) — used by
     * DocNodeResource so the admin table reads as a tree instead of one
     * flat, parent-and-child-interleaved list.
     */
    public static function treeOrderedIds(): array
    {
        $byParent = static::query()
            ->orderBy('sort_order')
            ->get(['id', 'parent_id'])
            ->groupBy('parent_id');

        $ids = [];
        $walk = function (?int $parentId) use (&$walk, &$ids, $byParent): void {
            foreach ($byParent->get($parentId, collect()) as $node) {
                $ids[] = $node->id;
                $walk($node->id);
            }
        };
        $walk(null);

        return $ids;
    }
}
