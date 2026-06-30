<?php

namespace App\Http\Controllers;

use App\Models\DocNode;

class DocsController extends Controller
{
    // /docs -> grid of top-level categories (root sections)
    public function index()
    {
        $docs = DocNode::roots()->active()
            ->orderBy('sort_order')
            ->get();

        return view('docs.index', compact('docs'));
    }

    // /docs/{slug} -> a section: its sub-sections (as tabs) + documents
    public function show(string $slug)
    {
        $current = DocNode::where('slug', $slug)->active()->firstOrFail();

        // Sub-sections become tabs; eager-load each one's visible documents
        // (and its own sub-sections, for deeper drill-down links).
        $children = $current->activeChildren()
            ->with([
                'attachments' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order'),
                'activeChildren',
            ])
            ->get();

        // Documents that belong directly to this section.
        $attachments = $current->attachments()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        // Breadcrumb: Dokumentacija › ancestors › current
        $breadcrumb = collect([
            (object) ['title' => 'Dokumentacija', 'url' => route('docs.index')],
        ]);
        foreach ($current->ancestors() as $ancestor) {
            $breadcrumb->push((object) ['title' => $ancestor->title, 'url' => route('docs.show', $ancestor->slug)]);
        }
        $breadcrumb->push((object) ['title' => $current->title, 'url' => null]);

        return view('docs.show', compact('current', 'children', 'attachments', 'breadcrumb'));
    }
}
