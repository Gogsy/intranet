<?php

namespace App\Http\Controllers;

use App\Models\Tool;
use App\Models\ToolClick;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ToolsController extends Controller
{
    public function index()
    {
        $tools = Tool::where('is_visible', true)
            ->orderBy('sort_order')   // <- primarni redoslijed (iz Filament drag&drop-a)
            ->orderBy('name')         // <- sekundarni; stabilizira
            ->get(['id','name','url','icon','sort_order']);

        return view('tools_index', compact('tools'));
    }

    /** Logs the click, then sends the user on to the tool's real URL. */
    public function click(Request $request, Tool $tool): RedirectResponse
    {
        ToolClick::create([
            'tool_id' => $tool->id,
            'user_id' => auth()->id(),
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
        ]);

        return redirect()->away($tool->url);
    }
}