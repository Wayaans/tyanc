<?php

declare(strict_types=1);

it('unwraps computed ref values before passing to DataTableToolbar props', function (): void {
    $component = file_get_contents(resource_path('js/components/admin/DataTable.vue'));

    // Refs returned from useDataTableQuery() live on a plain object, so they
    // are NOT auto-unwrapped in the template. The bindings must access .value
    // explicitly to pass a number rather than a ComputedRef<number>.
    expect($component)
        ->toContain(':active-filters="tableQuery.appliedFilters.value"')
        ->toContain(':draft-filters="tableQuery.draftFilters.value"')
        ->toContain(':active-filter-count="tableQuery.activeFilterCount.value"')
        ->toContain(':selected-row-count="tableQuery.selectedRowCount.value"')
        ->not->toContain(':active-filters="tableQuery.appliedFilters"')
        ->not->toContain(':draft-filters="tableQuery.draftFilters"')
        ->not->toContain(':active-filter-count="tableQuery.activeFilterCount"')
        ->not->toContain(':selected-row-count="tableQuery.selectedRowCount"');
});
