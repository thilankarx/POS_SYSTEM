<?php

declare(strict_types=1);

namespace App\Livewire\Giftcards;

use App\Domain\Crm\Models\Customer;
use App\Domain\Giftcards\Actions\IssueGiftcardAction;
use App\Domain\Giftcards\Actions\TopUpGiftcardAction;
use App\Domain\Giftcards\Exceptions\GiftcardException;
use App\Domain\Giftcards\Models\Giftcard;
use App\Support\Logging\DomainLog;
use App\Support\Money\Rules\ValidMoneyAmount;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Form extends Component
{
    public ?Giftcard $giftcard = null;

    public string $initial_value = '';

    public ?int $customer_id = null;

    public ?string $expires_at = null;

    public string $topUpAmount = '';

    public function mount(?Giftcard $giftcard = null): void
    {
        $this->giftcard = $giftcard;

        if ($giftcard !== null) {
            Gate::authorize('view', $giftcard);
        } else {
            Gate::authorize('create', Giftcard::class);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'initial_value' => ['required', new ValidMoneyAmount],
            'customer_id' => ['nullable', Rule::exists('customers', 'id')],
            'expires_at' => ['nullable', 'date'],
        ];
    }

    public function issue(): void
    {
        Gate::authorize('create', Giftcard::class);

        $validated = $this->validate();

        $this->giftcard = app(IssueGiftcardAction::class)->execute(
            initialValue: $validated['initial_value'],
            customer: $validated['customer_id'] ? Customer::find($validated['customer_id']) : null,
            expiresAt: $validated['expires_at'] ?: null,
        );

        session()->flash('status', 'Gift card issued.');
        $this->redirectRoute('giftcards.edit', $this->giftcard);
    }

    public function topUp(): void
    {
        Gate::authorize('update', $this->giftcard);

        $validated = $this->validate(['topUpAmount' => ['required', new ValidMoneyAmount]]);

        try {
            app(TopUpGiftcardAction::class)->execute($this->giftcard, $validated['topUpAmount'], auth()->user());
        } catch (GiftcardException $e) {
            DomainLog::refused($e, ['giftcard_id' => $this->giftcard?->id]);
            $this->addError('topUpAmount', $e->getMessage());

            return;
        }

        $this->topUpAmount = '';
        $this->giftcard->refresh();
        session()->flash('status', 'Gift card topped up.');
    }

    public function render()
    {
        return view('livewire.giftcards.form', [
            'customers' => Customer::with('person')->orderBy('company_name')->limit(200)->get(),
            'transactions' => $this->giftcard?->transactions()->latest()->get() ?? collect(),
        ]);
    }
}
