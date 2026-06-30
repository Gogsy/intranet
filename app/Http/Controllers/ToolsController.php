<?php

namespace App\Http\Controllers;

use App\Models\Tool;

class ToolsController extends Controller
{
    public function index()
    {
        $tools = Tool::where('is_visible', true)
            ->orderBy('sort_order')   // <- primarni redoslijed (iz Filament drag&drop-a)
            ->orderBy('name')         // <- sekundarni; stabilizira
            ->get(['name','url','icon','sort_order']);

        return view('tools_index', compact('tools'));
    }
}