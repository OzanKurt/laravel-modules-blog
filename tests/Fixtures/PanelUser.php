<?php

declare(strict_types=1);

namespace Kurt\Modules\Blog\Tests\Fixtures;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Authenticatable user for the Filament panel smoke tests. Implements
 * FilamentUser so panel-access checks pass, and lives on the shared `users`
 * table created by the base test case.
 */
final class PanelUser extends Authenticatable implements FilamentUser
{
    protected $table = 'users';

    protected $guarded = [];

    public $timestamps = false;

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }
}
