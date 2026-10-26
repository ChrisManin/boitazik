{{-- Same pagination as below the table: long lists can be browsed without scrolling down. $this is the Livewire table component --}}
@php
    $table = $this->getTable();
    $records = $this->getTableRecords();
@endphp

@if ($table->isPaginated() && $records instanceof \Illuminate\Contracts\Pagination\Paginator && $records->isNotEmpty())
    <x-filament::pagination
        :extreme-links="$table->hasExtremePaginationLinks()"
        :page-options="$table->getPaginationPageOptions()"
        :paginator="$records"
        class="fi-ta-pagination border-b border-gray-200 px-3 py-3 sm:px-6 dark:border-white/10"
    />
@endif
