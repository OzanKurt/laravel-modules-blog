<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Kurt\Modules\Blog\Models\Category;
use Kurt\Modules\Blog\Tests\Fixtures\PanelUser;

it('performs full category CRUD as staff', function () {
    $staff = PanelUser::create(['email' => 'staff@example.com']);
    Gate::define('canManageBlog', fn ($user) => (int) $user->getAuthIdentifier() === (int) $staff->id);
    $this->actingAs($staff);

    $created = $this->postJson('/api/blog/categories', ['name' => 'News'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'News');

    $id = $created->json('data.id');

    $this->getJson('/api/blog/categories/'.$id)->assertOk()->assertJsonPath('data.name', 'News');

    $this->patchJson('/api/blog/categories/'.$id, ['name' => 'Updates'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Updates');

    $this->deleteJson('/api/blog/categories/'.$id)->assertNoContent();

    expect(Category::withTrashed()->findOrFail($id)->trashed())->toBeTrue();
});

it('lists categories publicly', function () {
    Category::factory()->create();

    $this->getJson('/api/blog/categories')
        ->assertOk()
        ->assertJsonPath('meta.pagination.total', 1);
});
