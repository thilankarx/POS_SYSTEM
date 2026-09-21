<?php

declare(strict_types=1);

namespace App\Livewire\Catalog\AttributeDefinitions;

use App\Domain\Catalog\Models\AttributeDefinition;
use App\Domain\Catalog\Models\Item;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $type = '';

    #[Url(except: '')]
    public string $level = '';

    #[Url(except: '')]
    public string $visibility = '';

    #[Url(except: '')]
    public string $usage = '';

    #[Url(except: 'name')]
    public string $sort = 'name';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingType(): void
    {
        $this->resetPage();
    }

    public function updatingLevel(): void
    {
        $this->resetPage();
    }

    public function updatingVisibility(): void
    {
        $this->resetPage();
    }

    public function updatingUsage(): void
    {
        $this->resetPage();
    }

    public function updatingSort(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'type', 'level', 'visibility', 'usage']);
        $this->sort = 'name';
        $this->resetPage();
    }

    public function delete(int $id): void
    {
        $attributeDefinition = AttributeDefinition::findOrFail($id);

        Gate::authorize('delete', $attributeDefinition);

        $attributeDefinition->delete();

        session()->flash('status', 'Attribute deleted.');
    }

    private function filteredQuery(): Builder
    {
        $search = trim($this->search);

        return AttributeDefinition::query()
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('unit', 'like', "%{$search}%")
                        ->orWhereHas('parent', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($this->type !== '', fn (Builder $query) => $query->where('type', $this->type))
            ->when($this->level === 'top', fn (Builder $query) => $query->whereNull('parent_id'))
            ->when($this->level === 'child', fn (Builder $query) => $query->whereNotNull('parent_id'))
            ->when($this->visibility === 'search', fn (Builder $query) => $query->where('show_in_search', true))
            ->when($this->visibility === 'receipt', fn (Builder $query) => $query->where('show_in_receipt', true))
            ->when($this->visibility === 'hidden', fn (Builder $query) => $query->where('show_in_search', false)->where('show_in_receipt', false))
            ->when($this->usage === 'used', fn (Builder $query) => $query->whereExists($this->assignmentExistsQuery()))
            ->when($this->usage === 'unused', fn (Builder $query) => $query->whereNotExists($this->assignmentExistsQuery()));
    }

    private function assignmentExistsQuery(): QueryBuilder
    {
        return DB::table('attribute_values')
            ->selectRaw('1')
            ->join('attribute_links', 'attribute_links.attribute_value_id', '=', 'attribute_values.id')
            ->whereColumn('attribute_values.attribute_definition_id', 'attribute_definitions.id')
            ->where('attribute_links.attributable_type', (new Item)->getMorphClass());
    }

    public function render(): View
    {
        Gate::authorize('viewAny', AttributeDefinition::class);

        $query = $this->filteredQuery()
            ->with('parent')
            ->withCount(['values', 'children'])
            ->addSelect([
                'assignments_count' => DB::table('attribute_links')
                    ->selectRaw('count(*)')
                    ->join('attribute_values', 'attribute_values.id', '=', 'attribute_links.attribute_value_id')
                    ->whereColumn('attribute_values.attribute_definition_id', 'attribute_definitions.id')
                    ->where('attribute_links.attributable_type', (new Item)->getMorphClass()),
            ]);

        match ($this->sort) {
            'type' => $query->orderBy('type')->orderBy('name'),
            'usage' => $query->orderByDesc('assignments_count')->orderBy('name'),
            'newest' => $query->latest(),
            default => $query->orderBy('name'),
        };

        $attributeDefinitions = $query->paginate(20);
        $summaryQuery = $this->filteredQuery();

        return view('livewire.catalog.attribute-definitions.index', [
            'attributeDefinitions' => $attributeDefinitions,
            'summary' => [
                'total' => (clone $summaryQuery)->count(),
                'groups' => (clone $summaryQuery)->where('type', AttributeDefinition::TYPE_GROUP)->count(),
                'searchable' => (clone $summaryQuery)->where('show_in_search', true)->count(),
                'assignments' => DB::table('attribute_links')
                    ->join('attribute_values', 'attribute_values.id', '=', 'attribute_links.attribute_value_id')
                    ->where('attribute_links.attributable_type', (new Item)->getMorphClass())
                    ->whereIn('attribute_values.attribute_definition_id', (clone $summaryQuery)->select('attribute_definitions.id'))
                    ->count(),
            ],
        ]);
    }
}
