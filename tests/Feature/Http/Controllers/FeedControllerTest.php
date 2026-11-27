<?php

use App\Models\Folder;
use App\Models\Product;

test('the RSS feed lists published files as valid XML with titles escaped', function () {
    $product = Product::factory()->published()->create(['title' => 'Tools & Drivers <v2>']);
    Product::factory()->create(['title' => 'Draft File']);

    $response = $this->get(route('rss'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/rss+xml; charset=UTF-8');

    $feed = simplexml_load_string($response->getContent());

    expect($feed)->not->toBeFalse()
        ->and((string) $feed->channel->item[0]->title)->toBe('Tools & Drivers <v2>')
        ->and((string) $feed->channel->item[0]->link)->toBe(route('products.show', $product))
        ->and($feed->channel->item)->toHaveCount(1);
});

test('the sitemap lists visible folders and files only', function () {
    $visibleFolder = Folder::factory()->create();
    $hiddenFolder = Folder::factory()->hidden()->create();
    $visibleProduct = Product::factory()->published()->for($visibleFolder)->create();
    $productInHiddenFolder = Product::factory()->published()->for($hiddenFolder)->create();

    $response = $this->get(route('sitemap'))->assertOk();
    $sitemap = simplexml_load_string($response->getContent());

    expect($sitemap)->not->toBeFalse();

    // Every element is named <url>, so keys must be dropped or each entry overwrites the last.
    $locations = collect(iterator_to_array($sitemap->url, false))->map(fn ($url): string => (string) $url->loc)->all();

    expect($locations)->toContain(route('folders.show', $visibleFolder), route('products.show', $visibleProduct))
        ->not->toContain(route('folders.show', $hiddenFolder), route('products.show', $productInHiddenFolder));
});
