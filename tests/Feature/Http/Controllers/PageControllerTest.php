<?php

use App\Models\Page;

test('a published page is shown and linked from the footer', function () {
    $page = Page::factory()->create(['title' => 'Refund policy', 'body' => 'Refunds go to your **wallet**.']);

    $this->get(route('pages.show', $page))
        ->assertOk()
        ->assertSee('<strong>wallet</strong>', escape: false);

    $this->get(route('home'))->assertSee(route('pages.show', $page));
});

test('a draft page is not found and not linked', function () {
    $page = Page::factory()->draft()->create(['title' => 'Terms draft']);

    $this->get(route('pages.show', $page))->assertNotFound();
    $this->get(route('home'))->assertDontSee('Terms draft');
});
