<?php

declare(strict_types=1);

namespace Kurt\Modules\Blog\Tests;

use Cviebrock\EloquentSluggable\ServiceProvider as SluggableServiceProvider;
use Filament\Actions\ActionsServiceProvider;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Illuminate\Foundation\Application;
use Kurt\Modules\Blog\Providers\BlogServiceProvider;
use Kurt\Modules\Blog\Tests\Fixtures\AdminPanelProvider;
use Kurt\Modules\Core\Providers\CoreServiceProvider;
use Kurt\Modules\Core\Support\FilamentVersion;
use Kurt\Modules\Core\Testing\PackageTestCase;
use Kurt\Modules\Interactions\Providers\InteractionsServiceProvider;
use Livewire\LivewireServiceProvider;
use Spatie\MediaLibrary\MediaLibraryServiceProvider;

abstract class TestCase extends PackageTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Under Testbench the Livewire mechanism singletons (notably the
        // DataStore that backs each component's error bag) lose their shared
        // container binding after the provider's initial registration, which
        // makes every Filament/Livewire component render throw on a null error
        // bag. Re-running the provider's register() is idempotent and restores
        // those singletons so the panel smoke tests can render. No-op when
        // Filament (and therefore Livewire) is not installed.
        if (FilamentVersion::major() !== null && class_exists(LivewireServiceProvider::class)) {
            (new LivewireServiceProvider($this->app))->register();
        }
    }

    /**
     * @param  Application  $app
     * @return array<int, class-string>
     */
    protected function modulePackageProviders($app): array
    {
        return array_merge([
            SluggableServiceProvider::class,
            MediaLibraryServiceProvider::class,
            InteractionsServiceProvider::class,
            BlogServiceProvider::class,
        ], $this->filamentProviders());
    }

    /**
     * @param  Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return array_merge([
            CoreServiceProvider::class,
            SluggableServiceProvider::class,
            MediaLibraryServiceProvider::class,
            InteractionsServiceProvider::class,
            BlogServiceProvider::class,
        ], $this->filamentProviders());
    }

    /**
     * Filament + Livewire providers are only registered when Filament is
     * installed, so the non-Filament suites run without them. Each provider
     * is filtered by class_exists so the list stays valid across the v3/v4/v5
     * matrix (e.g. filament/schemas and filament/actions are v4/v5-only
     * packages).
     *
     * @return array<int, class-string>
     */
    protected function filamentProviders(): array
    {
        if (FilamentVersion::major() === null) {
            return [];
        }

        $candidates = [
            LivewireServiceProvider::class,
            SupportServiceProvider::class,
            ActionsServiceProvider::class,
            FormsServiceProvider::class,
            InfolistsServiceProvider::class,
            NotificationsServiceProvider::class,
            SchemasServiceProvider::class,
            TablesServiceProvider::class,
            WidgetsServiceProvider::class,
            FilamentServiceProvider::class,
            AdminPanelProvider::class,
        ];

        return array_values(array_filter(
            $candidates,
            static fn (string $provider): bool => class_exists($provider),
        ));
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // Filament's Livewire components need a session + app key so the
        // validation error bag and CSRF are available during component tests.
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('session.driver', 'array');
    }

    protected function defineDatabaseMigrations(): void
    {
        parent::defineDatabaseMigrations();

        // Medialibrary's media table (needed by Post HasMedia).
        $this->loadMigrationsFrom(__DIR__.'/migrations');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // The Interactions package owns the comment store that Blog comments now
        // live in; load its migrations so interactions_comments (and the
        // blog→interactions data migration) are present during tests.
        $this->loadMigrationsFrom(dirname(
            (string) (new \ReflectionClass(InteractionsServiceProvider::class))->getFileName(),
            3,
        ).'/database/migrations');
    }
}
