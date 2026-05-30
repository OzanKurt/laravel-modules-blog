<?php

declare(strict_types=1);

namespace Kurt\Modules\Blog\Tests\Fixtures;

use Filament\Panel;
use Filament\PanelProvider;
use Kurt\Modules\Blog\Filament\BlogPlugin;

/**
 * Minimal Filament panel used by the resource smoke tests. It registers the
 * version-dispatching Blog plugin so the correct V{n} resource set is wired
 * up for whichever Filament major is installed in the current CI matrix cell.
 */
final class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('admin')
            ->path('admin')
            ->default()
            ->plugin(BlogPlugin::make());
    }
}
