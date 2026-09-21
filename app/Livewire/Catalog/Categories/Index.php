<?php

declare(strict_types=1);

namespace App\Livewire\Catalog\Categories;

use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Item;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
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
    public string $level = '';

    #[Url(except: '')]
    public string $usage = '';

    #[Url(except: 'name')]
    public string $sort = 'name';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingLevel(): void
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
        $this->reset(['search', 'level', 'usage']);
        $this->sort = 'name';
        $this->resetPage();
    }

    public function delete(int $id): void
    {
        $category = Category::findOrFail($id);

        Gate::authorize('delete', $category);

        $category->delete();

        session()->flash('status', 'Category deleted.');
    }

    private function filteredQuery(): Builder
    {
        $search = trim($this->search);

        return Category::query()
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhereHas('parent', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($this->level === 'top', fn (Builder $query) => $query->whereNull('parent_id'))
            ->when($this->level === 'child', fn (Builder $query) => $query->whereNotNull('parent_id'))
            ->when($this->usage === 'used', fn (Builder $query) => $query->has('items'))
            ->when($this->usage === 'empty', fn (Builder $query) => $query->doesntHave('items'));
    }

    public function render(): View
    {
        Gate::authorize('viewAny', Category::class);

        $query = $this->filteredQuery()
            ->with('parent')
            ->withCount(['items', 'children']);

        match ($this->sort) {
            'code' => $query->orderBy('code')->orderBy('name'),
            'items' => $query->orderByDesc('items_count')->orderBy('name'),
            'newest' => $query->latest(),
            default => $query->orderBy('name'),
        };

        $categories = $query->paginate(20);
        $summaryQuery = $this->filteredQuery();

        return view('livewire.catalog.categories.index', [
            'categories' => $categories,
            'summary' => [
                'total' => (clone $summaryQuery)->count(),
                'topLevel' => (clone $summaryQuery)->whereNull('parent_id')->count(),
                'subcategories' => (clone $summaryQuery)->whereNotNull('parent_id')->count(),
                'items' => Item::query()->whereIn('category_id', (clone $summaryQuery)->select('categories.id'))->count(),
            ],
        ]);
    }
}
