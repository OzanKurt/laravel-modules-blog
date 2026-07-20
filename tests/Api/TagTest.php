<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Kurt\Modules\Blog\Models\Tag;
use Kurt\Modules\Blog\Tests\Fixtures\PanelUser;

it('lists and shows tags publicly', function () {
    $tag = Tag::factory()->create();

    $this->getJson('/api/blog/tags')
        ->assertOk()
        ->assertJsonPath('meta.pagination.total', 1);

    $this->getJson('/api/blog/tags/'.$tag->id)
        ->assertOk()
        ->assertJsonPath('data.id', $tag->id);
});

it('lets staff create and delete tags', function () {
    $staff = PanelUser::create(['email' => 'staff@example.com']);
    Gate::define('canManageBlog', fn ($user) => (int) $user->getAuthIdentifier() === (int) $staff->id);
    $this->actingAs($staff);

    $created = $this->postJson('/api/blog/tags', ['name' => 'laravel'])->assertCreated();

    $this->deleteJson('/api/blog/tags/'.$created->json('data.id'))->assertNoContent();
});
