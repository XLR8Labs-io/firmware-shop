<?php

use App\Filament\Resources\Announcements\AnnouncementResource;
use App\Filament\Resources\Announcements\Pages\ManageAnnouncements;
use App\Filament\Resources\Articles\ArticleResource;
use App\Filament\Resources\Articles\Pages\CreateArticle;
use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Filament\Resources\FileRequests\FileRequestResource;
use App\Filament\Resources\Pages\PageResource;
use App\Filament\Resources\Pages\Pages\CreatePage;
use App\Filament\Resources\Resellers\Pages\ManageResellers;
use App\Filament\Resources\Resellers\ResellerResource;
use App\Models\Announcement;
use App\Models\Article;
use App\Models\Page;
use App\Models\Reseller;
use App\Models\User;
use Livewire\Livewire;

test('customers cannot open the content screens', function (string $url) {
    $this->actingAs(User::factory()->withTwoFactor()->create())->get($url)->assertForbidden();
})->with([
    'announcements' => fn () => AnnouncementResource::getUrl('index'),
    'blog posts' => fn () => ArticleResource::getUrl('index'),
    'pages' => fn () => PageResource::getUrl('index'),
    'file requests' => fn () => FileRequestResource::getUrl('index'),
    'world agents' => fn () => ResellerResource::getUrl('index'),
]);

test('an admin opens every content screen', function (string $url) {
    actingAsAdmin();

    $this->get($url)->assertOk();
})->with([
    'announcements' => fn () => AnnouncementResource::getUrl('index'),
    'blog posts' => fn () => ArticleResource::getUrl('index'),
    'new blog post' => fn () => ArticleResource::getUrl('create'),
    'pages' => fn () => PageResource::getUrl('index'),
    'new page' => fn () => PageResource::getUrl('create'),
    'file requests' => fn () => FileRequestResource::getUrl('index'),
    'world agents' => fn () => ResellerResource::getUrl('index'),
]);

test('an admin publishes an announcement to the notice bar', function () {
    actingAsAdmin();

    Livewire::test(ManageAnnouncements::class)
        ->callAction('create', data: [
            'title' => 'Prices change on Monday',
            'body' => 'New prices for **Samsung** files.',
            'show_in_ticker' => true,
            'is_published' => true,
        ])
        ->assertHasNoFormErrors();

    $announcement = Announcement::query()->sole();

    expect($announcement->isPublic())->toBeTrue();
    $this->get(route('home'))->assertSee('Prices change on Monday');
});

test('an admin writes a blog post and later edits it', function () {
    actingAsAdmin();

    Livewire::test(CreateArticle::class)
        ->fillForm([
            'title' => 'How to read a Samsung firmware name',
            'body' => 'The **CSC** part tells you the region.',
            'is_published' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $article = Article::query()->sole();
    expect($article->slug)->toBe('how-to-read-a-samsung-firmware-name');

    Livewire::test(EditArticle::class, ['record' => $article->getRouteKey()])
        ->fillForm(['title' => 'Reading Samsung firmware names'])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->get(route('blog.show', $article))->assertOk()->assertSee('Reading Samsung firmware names');
});

test('an admin creates a page with its own address', function () {
    actingAsAdmin();

    Livewire::test(CreatePage::class)
        ->fillForm(['title' => 'How to buy', 'slug' => 'how-to-buy', 'body' => 'Add funds, then buy.', 'is_published' => true])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Page::query()->sole()->slug)->toBe('how-to-buy');
    $this->get('/pages/how-to-buy')->assertOk()->assertSee('Add funds, then buy.');
});

test('one account can belong to only one agent', function () {
    actingAsAdmin();
    $account = User::factory()->create();
    Reseller::factory()->withAccount($account)->create();

    Livewire::test(ManageResellers::class)
        ->callAction('create', data: ['name' => 'Second agent', 'country' => 'Nepal', 'user_id' => $account->id])
        ->assertHasFormErrors(['user_id' => 'unique']);

    expect(Reseller::query()->count())->toBe(1);
});
