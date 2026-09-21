<?php

declare(strict_types=1);

namespace App\Livewire\Catalog\Categories;

use App\Domain\Catalog\Models\Category;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Form extends Component
{
    public ?Category $category = null;

    public string $name = '';

    public string $slug = '';

    public string $code = '';

    public ?int $parent_id = null;

    public function mount(?Category $category = null): void
    {
        $this->category = $category;

        if ($category !== null) {
            Gate::authorize('update', $category);

            $this->name = $category->name;
            $this->slug = $category->slug;
            $this->code = (string) $category->code;
            $this->parent_id = $category->parent_id;
        } else {
            Gate::authorize('create', Category::class);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('categories', 'slug')->ignore($this->category?->id)],
            'code' => ['nullable', 'string', 'max:10', Rule::unique('categories', 'code')->ignore($this->category?->id)],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id'),
                function (string $attribute, mixed $value, \Closure $fail) {
                    if ($this->category !== null && (int) $value === $this->category->id) {
                        $fail('A category cannot be its own parent.');
                    }
                },
            ],
        ];
    }

    public function save(): void
    {
        $this->slug = $this->slugPreview();
        $this->code = $this->codePreview();

        $validated = $this->validate();

        if ($this->category !== null) {
            Gate::authorize('update', $this->category);
            $this->category->update($validated);
        } else {
            Gate::authorize('create', Category::class);
            Category::create($validated);
        }

        session()->flash('status', 'Category saved.');
        $this->redirectRoute('categories.index');
    }

    private function slugPreview(): string
    {
        return Str::slug($this->slug !== '' ? $this->slug : $this->name);
    }

    private function codePreview(): string
    {
        if ($this->code !== '') {
            return strtoupper($this->code);
        }

        $derived = strtoupper(Str::of($this->name)->replaceMatches('/[^A-Za-z0-9]/', '')->substr(0, 4)->value());

        return $derived !== '' ? $derived : 'CAT';
    }

    public function render(): View
    {
        $categories = Category::query()
            ->when($this->category, fn ($q) => $q->whereKeyNot($this->category->id))
            ->with('parent')
            ->withCount('items')
            ->orderBy('name')
            ->get();

        return view('livewire.catalog.categories.form', [
            'categories' => $categories,
            'selectedParent' => $categories->firstWhere('id', $this->parent_id),
            'slugPreview' => $this->slugPreview(),
            'codePreview' => $this->codePreview(),
        ]);
    }
}
