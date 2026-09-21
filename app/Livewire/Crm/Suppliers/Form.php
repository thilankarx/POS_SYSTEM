<?php

declare(strict_types=1);

namespace App\Livewire\Crm\Suppliers;

use App\Domain\Crm\Models\Supplier;
use App\Domain\Identity\Models\Person;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Form extends Component
{
    public ?Supplier $supplier = null;

    public string $first_name = '';

    public string $last_name = '';

    public string $email = '';

    public string $phone = '';

    public string $company_name = '';

    public string $agency_name = '';

    public string $account_number = '';

    public string $tax_number = '';

    public string $supplier_type = 'goods';

    public int $lead_time_days = 0;

    public function mount(?Supplier $supplier = null): void
    {
        $this->supplier = $supplier;

        if ($supplier !== null) {
            Gate::authorize('update', $supplier);

            $supplier->loadMissing('person');
            $this->first_name = $supplier->person->first_name;
            $this->last_name = $supplier->person->last_name;
            $this->email = (string) $supplier->person->email;
            $this->phone = (string) $supplier->person->phone;
            $this->company_name = $supplier->company_name;
            $this->agency_name = (string) $supplier->agency_name;
            $this->account_number = (string) $supplier->account_number;
            $this->tax_number = (string) $supplier->tax_number;
            $this->supplier_type = $supplier->supplier_type;
            $this->lead_time_days = $supplier->lead_time_days;
        } else {
            Gate::authorize('create', Supplier::class);
        }
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'company_name' => ['required', 'string', 'max:255'],
            'agency_name' => ['nullable', 'string', 'max:255'],
            'account_number' => ['nullable', 'string', 'max:255', Rule::unique('suppliers', 'account_number')->ignore($this->supplier?->id)],
            'tax_number' => ['nullable', 'string', 'max:64'],
            'supplier_type' => ['required', Rule::in(['goods', 'expense'])],
            'lead_time_days' => ['required', 'integer', 'min:0'],
        ];
    }

    public function save(): void
    {
        $validated = $this->validate();

        $personFields = collect($validated)->only(['first_name', 'last_name', 'email', 'phone'])->all();
        $supplierFields = collect($validated)->except(['first_name', 'last_name', 'email', 'phone'])->all();

        // account_number is nullable+unique so more than one supplier can go
        // without one -- but Laravel's `unique` rule (not being "implicit")
        // never even runs against an empty string, so blank values sail
        // through validation and only collide once they hit the DB's unique
        // index, which treats '' as a real, repeatable-only-once value
        // (unlike NULL). Storing NULL for "no account number" is what the
        // nullable column actually intends, and is what lets a second blank
        // supplier save at all.
        $supplierFields['account_number'] = $supplierFields['account_number'] !== '' ? $supplierFields['account_number'] : null;

        if ($this->supplier !== null) {
            Gate::authorize('update', $this->supplier);
            $this->supplier->person->update($personFields);
            $this->supplier->update($supplierFields);
        } else {
            Gate::authorize('create', Supplier::class);
            $person = Person::create($personFields);
            Supplier::create([...$supplierFields, 'person_id' => $person->id]);
        }

        session()->flash('status', 'Supplier saved.');
        $this->redirectRoute('suppliers.index');
    }

    public function render()
    {
        return view('livewire.crm.suppliers.form');
    }
}
