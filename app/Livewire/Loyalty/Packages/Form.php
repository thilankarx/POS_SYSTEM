<?php

declare(strict_types=1);

namespace App\Livewire\Loyalty\Packages;

use App\Domain\Loyalty\Models\LoyaltyPackage;
use App\Support\Money\Rules\ValidDecimal;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Form extends Component
{
    public ?LoyaltyPackage $loyaltyPackage = null;

    public string $name = '';

    public string $points_per_currency_unit = '0';

    public string $currency_value_per_point = '0';

    public ?string $points_expire_after_days = null;

    public bool $is_active = true;

    public function mount(?LoyaltyPackage $loyaltyPackage = null): void
    {
        $this->loyaltyPackage = $loyaltyPackage;

        if ($loyaltyPackage !== null) {
            Gate::authorize('update', $loyaltyPackage);

            $this->name = $loyaltyPackage->name;
            $this->points_per_currency_unit = (string) $loyaltyPackage->points_per_currency_unit;
            $this->currency_value_per_point = (string) $loyaltyPackage->currency_value_per_point;
            $this->points_expire_after_days = $loyaltyPackage->points_expire_after_days !== null
                ? (string) $loyaltyPackage->points_expire_after_days
                : null;
            $this->is_active = $loyaltyPackage->is_active;
        } else {
            Gate::authorize('create', LoyaltyPackage::class);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('loyalty_packages', 'name')->ignore($this->loyaltyPackage)],
            'points_per_currency_unit' => ['required', new ValidDecimal],
            'currency_value_per_point' => ['required', new ValidDecimal],
            'points_expire_after_days' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['boolean'],
        ];
    }

    public function save(): void
    {
        $validated = $this->validate();
        $validated['points_expire_after_days'] = $validated['points_expire_after_days'] !== null && $validated['points_expire_after_days'] !== ''
            ? $validated['points_expire_after_days']
            : null;

        if ($this->loyaltyPackage !== null) {
            Gate::authorize('update', $this->loyaltyPackage);
            $this->loyaltyPackage->update($validated);
        } else {
            Gate::authorize('create', LoyaltyPackage::class);
            $this->loyaltyPackage = LoyaltyPackage::create($validated);
        }

        session()->flash('status', 'Loyalty package saved.');
        $this->redirectRoute('loyalty-packages.index');
    }

    public function render()
    {
        return view('livewire.loyalty.packages.form');
    }
}
