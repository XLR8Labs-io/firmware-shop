<?php

namespace App\Http\Controllers;

use App\Models\Reseller;
use Illuminate\View\View;

class AgentController extends Controller
{
    public function index(): View
    {
        return view('agents.index', [
            'agents' => Reseller::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }
}
