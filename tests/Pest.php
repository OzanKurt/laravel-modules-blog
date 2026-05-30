<?php

declare(strict_types=1);

use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Kurt\Modules\Blog\Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature');

/*
|--------------------------------------------------------------------------
| Filament resource introspection helpers
|--------------------------------------------------------------------------
|
| Full Filament page rendering does not work under orchestra/testbench with
| the Filament v5 + Livewire v4 stack (the page Blade view loses its `$this`
| binding because Testbench drops Livewire's boot-time render hooks). These
| helpers let the version-guarded smoke tests assert the *structure* of a
| resource's form and table — proving the resource classes build with the
| correct components/columns/actions for each Filament major — without
| rendering a Livewire page. A booted page instance is used purely as the
| schema/table container.
|
*/

if (! function_exists('Kurt\Modules\Blog\Tests\formFieldNames')) {
    /**
     * @param  class-string  $resource  The resource class (form() is static).
     * @param  class-string  $pageClass  A page of the resource, used as the schema container.
     * @return array<int, string>
     */
    function formFieldNames(string $resource, string $pageClass): array
    {
        $schema = $resource::form(Schema::make(app($pageClass)));

        return array_keys($schema->getFlatFields(withHidden: true));
    }

    /**
     * @param  class-string  $resource
     * @param  class-string  $pageClass
     * @return array<int, string>
     */
    function tableColumnNames(string $resource, string $pageClass): array
    {
        $table = $resource::table(Table::make(app($pageClass)));

        return array_keys($table->getColumns());
    }

    /**
     * @param  class-string  $resource
     * @param  class-string  $pageClass
     * @return array<int, string>
     */
    function tableFilterNames(string $resource, string $pageClass): array
    {
        $table = $resource::table(Table::make(app($pageClass)));

        return array_keys($table->getFilters());
    }

    /**
     * @param  class-string  $resource
     * @param  class-string  $pageClass
     * @return array<int, string>
     */
    function tableBulkActionNames(string $resource, string $pageClass): array
    {
        $table = $resource::table(Table::make(app($pageClass)));

        return array_values(array_map(
            static fn ($action): string => $action->getName(),
            $table->getFlatBulkActions(),
        ));
    }

    /**
     * @param  class-string  $resource
     * @param  class-string  $pageClass
     * @return array<int, string>
     */
    function tableActionNames(string $resource, string $pageClass): array
    {
        $table = $resource::table(Table::make(app($pageClass)));

        return array_values(array_map(
            static fn ($action): string => $action->getName(),
            $table->getFlatActions(),
        ));
    }
}
