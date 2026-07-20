<?php

declare(strict_types=1);

// This test intentionally uses the default TestCase (not ApiTestCase), so
// blog.http.mode stays at its `headless` default and no API routes register.

it('registers no API routes in headless mode', function () {
    expect(config('blog.http.mode'))->toBe('headless');

    $this->getJson('/api/blog/posts')->assertNotFound();
    $this->postJson('/api/blog/posts', ['title' => 'x'])->assertNotFound();
});
