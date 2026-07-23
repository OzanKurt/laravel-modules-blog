<?php

declare(strict_types=1);

use Kurt\Modules\Core\Contracts\ModuleRegistry;

it('declares its manifest into the registry', function () {
    $r = app(ModuleRegistry::class);
    expect($r->has('blog'))->toBeTrue()
        ->and($r->get('blog')->getName())->toBe('Blog');
});
