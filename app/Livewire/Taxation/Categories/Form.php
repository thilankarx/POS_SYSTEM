<?php

declare(strict_types=1);

namespace App\Livewire\Taxation\Categories;

use App\Domain\Taxation\Models\TaxCategory;
use App\Domain\Taxation\Models\TaxJurisdiction;
use App\Domain\Taxation\Models\TaxRate;
use App\Support\Money\Rules\ValidDecimal;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Form extends Component
{
    public ?TaxCategory $taxCategory = null;

    public string $name = '';

    public string $code = '';

    public bool $is_default = false;

    /** @var array<int, array{id: ?int, name: string, rate: string, effective_from: ?string, effective_to: ?string}> */
    public array $rates = [];

    public function mount(?TaxCategory $taxCategory = null): void
    {
        $this->taxCategory = $taxCategory;

        if ($taxCategory !== null) {
            Gate::authorize('update', $taxCategory);

            $this->name = $taxCategory->name;
            $this->code = $taxCategory->code;
            $this->is_default = $taxCategory->is_default;
            $this->rates = $taxCategory->rates()->orderBy('cascade_sequence')->get()
                ->map(fn (TaxRate $rate) => [
                    'id' => $rate->id,
                    'name' => $rate->name,
                    'rate' => (string) $rate->rate,
                    'effective_from' => $rate->effective_from?->toDateString(),
                    'effective_to' => $rate->effective_to?->toDateString(),
                ])->all();
        } else {
            Gate::authorize('create', TaxCategory::class);
        }
    }

    public function addRate(): void
    {
        $this->rates[] = ['id' => null, 'name' => $this->name ?: '', 'rate' => '0.00', 'effective_from' => null, 'effective_to' => null];
    }

    public function removeRate(int $index): void
    {
        unset($this->rates[$index]);
        $this->rates = array_values($this->rates);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:32', Rule::unique('tax_categories', 'code')->ignore($this->taxCategory)],
            'is_default' => ['boolean'],
            'rates' => ['array'],
            'rates.*.id' => ['nullable', 'integer'],
            'rates.*.name' => ['required', 'string', 'max:255'],
            'rates.*.rate' => ['required', new ValidDecimal],
            'rates.*.effective_from' => ['nullable', 'date'],
            'rates.*.effective_to' => ['nullable', 'date', 'after_or_equal:rates.*.effective_from'],
        ];
    }

    public function save(): void
    {
        $validated = $this->validate();

        if ($this->taxCategory !== null) {
            Gate::authorize('update', $this->taxCategory);
            $this->taxCategory->update([
                'name' => $validated['name'],
                'code' => $validated['code'],
                'is_default' => $validated['is_default'],
            ]);
        } else {
            Gate::authorize('create', TaxCategory::class);
            $this->taxCategory = TaxCategory::create([
                'name' => $validated['name'],
                'code' => $validated['code'],
                'is_default' => $validated['is_default'],
            ]);
        }

        $this->syncRates($validated['rates']);

        session()->flash('status', 'Tax category saved.');
        $this->redirectRoute('tax-categories.index');
    }

    /**
     * @param  array<int, array{id: ?int, name: string, rate: string, effective_from: ?string, effective_to: ?string}>  $rates
     */
    private function syncRates(array $rates): void
    {
        $jurisdictionId = TaxJurisdiction::where('is_default', true)->value('id');
        $keptIds = [];

        foreach (array_values($rates) as $index => $row) {
            $attributes = [
                'tax_category_id' => $this->taxCategory->id,
                'tax_jurisdiction_id' => $jurisdictionId,
                'name' => $row['name'],
                'rate' => $row['rate'],
                'rounding_mode' => 'half_up',
                'cascade_sequence' => $index,
                'effective_from' => $row['effective_from'] ?: null,
                'effective_to' => $row['effective_to'] ?: null,
            ];

            if ($row['id'] !== null) {
                TaxRate::whereKey($row['id'])->update($attributes);
                $keptIds[] = $row['id'];
            } else {
                $keptIds[] = TaxRate::create($attributes)->id;
            }
        }

        $this->taxCategory->rates()->whereNotIn('id', $keptIds)->delete();
    }

    public function render()
    {
        return view('livewire.taxation.categories.form');
    }
}
