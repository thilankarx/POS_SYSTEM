<?php

declare(strict_types=1);

namespace App\Livewire\Crm\Customers;

use App\Domain\Crm\Models\Customer;
use App\Domain\Identity\Models\Person;
use App\Domain\Loyalty\Actions\AdjustPointsAction;
use App\Domain\Loyalty\Exceptions\LoyaltyException;
use App\Domain\Loyalty\Models\LoyaltyPackage;
use App\Domain\Taxation\Models\TaxCategory;
use App\Support\Logging\DomainLog;
use App\Support\Money\Rules\ValidMoneyAmount;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Form extends Component
{
    public ?Customer $customer = null;

    public string $first_name = '';

    public string $last_name = '';

    public string $email = '';

    public string $phone = '';

    public string $company_name = '';

    public string $account_number = '';

    public ?int $tax_category_id = null;

    public bool $is_tax_exempt = false;

    public string $credit_limit = '0.00';

    public bool $marketing_consent = false;

    public ?int $loyalty_package_id = null;

    public string $pointsAdjustment = '';

    public function mount(?Customer $customer = null): void
    {
        $this->customer = $customer;

        if ($customer !== null) {
            Gate::authorize('update', $customer);

            $customer->loadMissing('person');
            $this->first_name = $customer->person->first_name;
            $this->last_name = $customer->person->last_name;
            $this->email = (string) $customer->person->email;
            $this->phone = (string) $customer->person->phone;
            $this->company_name = (string) $customer->company_name;
            $this->account_number = (string) $customer->account_number;
            $this->tax_category_id = $customer->tax_category_id;
            $this->is_tax_exempt = $customer->is_tax_exempt;
            $this->credit_limit = (string) $customer->credit_limit->getAmount();
            $this->marketing_consent = $customer->marketing_consent;
            $this->loyalty_package_id = $customer->loyalty_package_id;
        } else {
            Gate::authorize('create', Customer::class);
        }
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'account_number' => ['nullable', 'string', 'max:255', Rule::unique('customers', 'account_number')->ignore($this->customer?->id)],
            'tax_category_id' => ['nullable', 'integer', Rule::exists('tax_categories', 'id')],
            'is_tax_exempt' => ['boolean'],
            'credit_limit' => ['required', new ValidMoneyAmount],
            'marketing_consent' => ['boolean'],
            'loyalty_package_id' => ['nullable', 'integer', Rule::exists('loyalty_packages', 'id')],
        ];
    }

    public function save(): void
    {
        $validated = $this->validate();

        $personFields = collect($validated)->only(['first_name', 'last_name', 'email', 'phone'])->all();
        $customerFields = collect($validated)->except(['first_name', 'last_name', 'email', 'phone'])->all();

        // account_number is nullable+unique so more than one customer can go
        // without one -- but Laravel's `unique` rule (not being "implicit")
        // never even runs against an empty string, so blank values sail
        // through validation and only collide once they hit the DB's unique
        // index, which treats '' as a real, repeatable-only-once value
        // (unlike NULL). Storing NULL for "no account number" is what the
        // nullable column actually intends, and is what lets a second blank
        // customer save at all.
        $customerFields['account_number'] = $customerFields['account_number'] !== '' ? $customerFields['account_number'] : null;

        if ($this->customer !== null) {
            Gate::authorize('update', $this->customer);
            $this->customer->person->update($personFields);
            $this->customer->update($customerFields);
        } else {
            Gate::authorize('create', Customer::class);
            $person = Person::create($personFields);
            Customer::create([...$customerFields, 'person_id' => $person->id]);
        }

        session()->flash('status', 'Customer saved.');
        $this->redirectRoute('customers.index');
    }

    public function adjustPoints(): void
    {
        Gate::authorize('loyalty.manage');

        $validated = $this->validate(['pointsAdjustment' => ['required', 'numeric', 'not_in:0']]);

        try {
            app(AdjustPointsAction::class)->execute($this->customer, $validated['pointsAdjustment'], auth()->user());
        } catch (LoyaltyException $e) {
            DomainLog::refused($e, ['customer_id' => $this->customer?->id]);
            $this->addError('pointsAdjustment', $e->getMessage());

            return;
        }

        $this->pointsAdjustment = '';
        $this->customer->refresh();
        session()->flash('status', 'Points adjusted.');
    }

    public function render()
    {
        return view('livewire.crm.customers.form', [
            'taxCategories' => TaxCategory::orderBy('name')->get(),
            'loyaltyPackages' => LoyaltyPackage::where('is_active', true)->orderBy('name')->get(),
        ]);
    }
}
