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

    /** Per-request cache for treeRowMeta() — the doc tree doesn't change mid-request. */
    private static ?array $treeMetaCache = null;

    /**
     * Depth-first tree layout metadata for every node, keyed by id (array
     * order IS the tree order — each parent immediately followed by its own
     * children, siblings by sort_order). Used by DocNodeResource to render
     * the admin table as an actual connected tree (├─ / └─ / │) instead of a
     * flat list where a child could land far from its parent with no visual
     * link to it.
     *
     * Each entry: ['depth' => int, 'isLast' => bool, 'ancestorIsLast' => bool[]]
     * — ancestorIsLast[$i] says whether the ancestor at depth $i was the
     * last child of ITS parent, i.e. whether that column's vertical line
     * should keep going past this row or stop.
     */
    public static function treeRowMeta(): array
    {
        return self::$treeMetaCache ??= self::buildTreeRowMeta();
    }

    private static function buildTreeRowMeta(): array
    {
        $byParent = static::query()
            ->orderBy('sort_order')
            ->get(['id', 'parent_id'])
            ->groupBy('parent_id');

        $meta = [];
        // $depth tracks recursion depth on its own — kept separate from
        // $ancestorIsLast because root nodes (depth 0) render no branch at
        // all, so their own isLast must NOT become a column for their
        // children (that would draw a spurious blank/bar before the first
        // real branch character).
        $walk = function (?int $parentId, array $ancestorIsLast, int $depth) use (&$walk, &$meta, $byParent): void {
            $siblings = $byParent->get($parentId, collect())->values();
            $count = $siblings->count();

            foreach ($siblings as $i => $node) {
                $isLast = $i === $count - 1;
                $meta[$node->id] = [
                    'depth' => $depth,
                    'isLast' => $isLast,
                    'ancestorIsLast' => $ancestorIsLast,
                ];
                $childAncestorIsLast = $depth === 0 ? $ancestorIsLast : [...$ancestorIsLast, $isLast];
                $walk($node->id, $childAncestorIsLast, $depth + 1);
            }
        };
        $walk(null, [], 0);

        return $meta;
    }
}
