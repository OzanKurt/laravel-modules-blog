<?php

declare(strict_types=1);

use Filament\Forms\Form;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Kurt\Modules\Blog\Tests\ApiTestCase;
use Kurt\Modules\Blog\Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature');

// The REST API suite lives in its own directory (sibling to Feature so it does
// not overlap the Feature-wide binding above) and uses ApiTestCase, which flips
// blog.http.mode to `api` before the providers boot so routes/api.php registers.
pest()->extend(ApiTestCase::class)->in('Api');

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

if (! function_exists('formFilamentContainer')) {
    /**
     * Build the form container Filament passes to a resource's static form().
     * v4/v5 use Filament\Schemas\Schema; v3 uses Filament\Forms\Form. Both
     * accept the owning Livewire component and expose getFlatFields().
     *
     * @param  class-string  $pageClass
     */
    function formFilamentContainer(string $pageClass): object
    {
        $container = class_exists(Schema::class)
            ? Schema::class
            : Form::class;

        return $container::make(app($pageClass));
    }

    /**
     * @param  class-string  $resource  The resource class (form() is static).
     * @param  class-string  $pageClass  A page of the resource, used as the form container.
     * @return array<int, string>
     */
    function formFieldNames(string $resource, string $pageClass): array
    {
        $form = $resource::form(formFilamentContainer($pageClass));

        return array_keys($form->getFlatFields(withHidden: true));
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
     * Recursively collect action names from a list that may contain
     * ActionGroup/BulkActionGroup wrappers.
     *
     * @param  iterable<mixed>  $actions
     * @return array<int, string>
     */
    function flattenActionNames(iterable $actions): array
    {
        $names = [];

        foreach ($actions as $action) {
            if (is_object($action) && method_exists($action, 'getName')) {
                $names[] = $action->getName();
            }

            if (is_object($action) && method_exists($action, 'getActions')) {
                $names = array_merge($names, flattenActionNames($action->getActions()));
            }
        }

        return array_values(array_filter($names));
    }

    /**
     * Bulk action names. v5 keeps them under getFlatBulkActions(); v4 stores
     * them in the toolbar (getToolbarActions()).
     *
     * @param  class-string  $resource
     * @param  class-string  $pageClass
     * @return array<int, string>
     */
    function tableBulkActionNames(string $resource, string $pageClass): array
    {
        $table = $resource::table(Table::make(app($pageClass)));

        if (method_exists($table, 'getFlatBulkActions')) {
            return flattenActionNames($table->getFlatBulkActions());
        }

        return flattenActionNames($table->getToolbarActions());
    }

    /**
     * Row action names. v5 keeps them under getFlatActions(); v4 stores them
     * in getRecordActions().
     *
     * @param  class-string  $resource
     * @param  class-string  $pageClass
     * @return array<int, string>
     */
    function tableActionNames(string $resource, string $pageClass): array
    {
        $table = $resource::table(Table::make(app($pageClass)));

        if (method_exists($table, 'getFlatActions')) {
            return flattenActionNames($table->getFlatActions());
        }

        return flattenActionNames($table->getRecordActions());
    }
}
