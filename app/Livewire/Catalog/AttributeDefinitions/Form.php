<?php

declare(strict_types=1);

namespace App\Livewire\Catalog\AttributeDefinitions;

use App\Domain\Catalog\Models\AttributeDefinition;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Form extends Component
{
    public ?AttributeDefinition $attributeDefinition = null;

    public string $name = '';

    public string $type = AttributeDefinition::TYPE_TEXT;

    public string $unit = '';

    public ?int $parent_id = null;

    public bool $show_in_receipt = false;

    public bool $show_in_search = false;

    public function updatedType(string $type): void
    {
        if ($type === AttributeDefinition::TYPE_GROUP) {
            $this->unit = '';
            $this->show_in_receipt = false;
            $this->show_in_search = false;
        }
    }

    public function mount(?AttributeDefinition $attributeDefinition = null): void
    {
        $this->attributeDefinition = $attributeDefinition;

        if ($attributeDefinition !== null) {
            Gate::authorize('update', $attributeDefinition);

            $this->name = $attributeDefinition->name;
            $this->type = $attributeDefinition->type;
            $this->unit = (string) $attributeDefinition->unit;
            $this->parent_id = $attributeDefinition->parent_id;
            $this->show_in_receipt = (bool) $attributeDefinition->show_in_receipt;
            $this->show_in_search = (bool) $attributeDefinition->show_in_search;
        } else {
            Gate::authorize('create', AttributeDefinition::class);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in([
                AttributeDefinition::TYPE_TEXT, AttributeDefinition::TYPE_DROPDOWN,
                AttributeDefinition::TYPE_DECIMAL, AttributeDefinition::TYPE_DATE,
                AttributeDefinition::TYPE_CHECKBOX, AttributeDefinition::TYPE_GROUP,
            ])],
            'unit' => ['nullable', 'string', 'max:16'],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('attribute_definitions', 'id'),
                function (string $attribute, mixed $value, \Closure $fail) {
                    if ($this->attributeDefinition !== null && (int) $value === $this->attributeDefinition->id) {
                        $fail('An attribute cannot be its own parent.');
                    }
                },
            ],
            'show_in_receipt' => ['boolean'],
            'show_in_search' => ['boolean'],
        ];
    }

    public function save(): void
    {
        $validated = $this->validate();
        $validated['unit'] = $validated['unit'] !== '' ? $validated['unit'] : null;

        if ($this->attributeDefinition !== null) {
            Gate::authorize('update', $this->attributeDefinition);
            $this->attributeDefinition->update($validated);
        } else {
            Gate::authorize('create', AttributeDefinition::class);
            AttributeDefinition::create($validated);
        }

        session()->flash('status', 'Attribute saved.');
        $this->redirectRoute('attribute-definitions.index');
    }

    public function render(): View
    {
        $attributeDefinitions = AttributeDefinition::query()
            ->when($this->attributeDefinition, fn ($q) => $q->whereKeyNot($this->attributeDefinition->id))
            ->with('parent')
            ->withCount('children')
            ->orderBy('name')
            ->get();

        return view('livewire.catalog.attribute-definitions.form', [
            'attributeDefinitions' => $attributeDefinitions,
            'selectedParent' => $attributeDefinitions->firstWhere('id', $this->parent_id),
        ]);
    }
}
