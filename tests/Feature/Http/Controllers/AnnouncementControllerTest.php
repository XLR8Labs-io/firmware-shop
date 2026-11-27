<?php

use App\Models\Announcement;

test('published announcements are listed and readable; drafts and scheduled ones are not', function () {
    $published = Announcement::factory()->create(['title' => 'Server maintenance on Friday', 'body' => 'Downloads pause for **one hour**.']);
    $draft = Announcement::factory()->draft()->create(['title' => 'Unfinished notice']);
    $scheduled = Announcement::factory()->create(['title' => 'Eid holiday hours', 'published_at' => now()->addDay()]);

    $this->get(route('announcements.index'))
        ->assertOk()
        ->assertSee('Server maintenance on Friday')
        ->assertDontSee('Unfinished notice')
        ->assertDontSee('Eid holiday hours');

    $this->get(route('announcements.show', $published))
        ->assertOk()
        ->assertSee('<strong>one hour</strong>', escape: false);

    $this->get(route('announcements.show', $draft))->assertNotFound();
    $this->get(route('announcements.show', $scheduled))->assertNotFound();
});

test('ticker announcements scroll on every page and link to themselves', function () {
    $inTicker = Announcement::factory()->inTicker()->create(['title' => 'Back up Persist before flashing']);
    Announcement::factory()->create(['title' => 'Not for the ticker']);
    Announcement::factory()->inTicker()->draft()->create(['title' => 'Draft ticker notice']);

    $this->get(route('blog.index'))
        ->assertSee('Back up Persist before flashing')
        ->assertSee(route('announcements.show', $inTicker))
        ->assertDontSee('Not for the ticker')
        ->assertDontSee('Draft ticker notice');
});

test('the home page shows the latest announcements', function () {
    Announcement::factory()->create(['title' => 'New Samsung files added']);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('New Samsung files added');
});

test('an announcement published without a time gets the current time', function () {
    $announcement = Announcement::factory()->draft()->create();

    $announcement->update(['is_published' => true]);

    expect($announcement->refresh()->published_at)->not->toBeNull()
        ->and($announcement->isPublic())->toBeTrue();
});
