<?php

use App\Models\Article;

test('published posts are listed newest first and open by their address', function () {
    Article::factory()->create(['title' => 'Older guide', 'published_at' => now()->subDays(3)]);
    $newest = Article::factory()->create(['title' => 'How to flash with Odin', 'body' => "## Steps\n\nOpen **Odin**."]);
    Article::factory()->draft()->create(['title' => 'Draft post']);

    $this->get(route('blog.index'))
        ->assertOk()
        ->assertSeeInOrder(['How to flash with Odin', 'Older guide'])
        ->assertDontSee('Draft post');

    $this->get('/blog/'.$newest->slug)
        ->assertOk()
        ->assertSee('How to flash with Odin')
        ->assertSee('<h2>Steps</h2>', escape: false);
});

test('draft and scheduled posts are not found', function (Closure $makeArticle) {
    $this->get(route('blog.show', $makeArticle()))->assertNotFound();
})->with([
    'draft' => [fn () => Article::factory()->draft()->create()],
    'scheduled' => [fn () => Article::factory()->create(['published_at' => now()->addHour()])],
]);

test('a post gets its address from its title', function () {
    $first = Article::factory()->create(['title' => 'Remove FRP on Galaxy A15']);
    $second = Article::factory()->create(['title' => 'Remove FRP on Galaxy A15']);

    expect($first->slug)->toBe('remove-frp-on-galaxy-a15')
        ->and($second->slug)->toBe('remove-frp-on-galaxy-a15-2');
});
