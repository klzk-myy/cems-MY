<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\View\Component;
use Illuminate\View\View;

class DataTable extends Component
{
    public function __construct(
        public ?LengthAwarePaginator $data = null,
        public array $columns = [],
        public bool $sortable = true,
        public bool $searchable = true,
        public string $emptyMessage = 'No records found',
    ) {}

    /**
     * Check if data has records
     */
    public function hasData(): bool
    {
        return $this->data && $this->data->isNotEmpty();
    }

    /**
     * Get column count for empty state colspan
     */
    public function getColumnCount(): int
    {
        return count($this->columns) + 1; // +1 for actions column
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): Closure|View
    {
        return function () {
            return view('components.data-table', [
                'hasData' => $this->hasData(),
                'columnCount' => $this->getColumnCount(),
            ]);
        };
    }
}
