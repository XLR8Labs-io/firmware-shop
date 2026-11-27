<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public function index(): View
    {
        return view('announcements.index', [
            'announcements' => Announcement::query()->published()->latest('published_at')->paginate(20),
        ]);
    }

    public function show(Announcement $announcement): View
    {
        abort_unless($announcement->isPublic(), 404);

        return view('announcements.show', ['announcement' => $announcement]);
    }
}
