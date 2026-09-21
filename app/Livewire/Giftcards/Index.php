<?php

declare(strict_types=1);

namespace App\Livewire\Giftcards;

use App\Domain\Giftcards\Exceptions\GiftcardException;
use App\Domain\Giftcards\Models\Giftcard;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public string $status = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function delete(int $id): void
    {
        $giftcard = Giftcard::findOrFail($id);

        Gate::authorize('delete', $giftcard);

        if (! $giftcard->balance->isEqualTo($giftcard->initial_value)) {
            session()->flash('error', GiftcardException::cannotDeleteUsed()->getMessage());

            return;
        }

        $giftcard->delete();

        session()->flash('status', 'Gift card deleted.');
    }

    public function render()
    {
        Gate::authorize('viewAny', Giftcard::class);

        $now = now();
        $stats = [
            'total' => Giftcard::count(),
            'available' => Giftcard::where('is_active', true)
                ->where('balance', '>', 0)
                ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>=', $now))
                ->count(),
            'expired' => Giftcard::where('is_active', true)->whereNotNull('expires_at')->where('expires_at', '<', $now)->count(),
            'depleted' => Giftcard::where('is_active', true)->where('balance', '<=', 0)->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>=', $now))->count(),
        ];

        $giftcards = Giftcard::query()
            ->with('customer.person')
            ->when($this->search !== '', fn ($query) => $query->where(function ($query) {
                $query->where('number', 'like', "%{$this->search}%")
                    ->orWhereHas('customer', fn ($customerQuery) => $customerQuery
                        ->where('company_name', 'like', "%{$this->search}%")
                        ->orWhereHas('person', fn ($personQuery) => $personQuery
                            ->where('first_name', 'like', "%{$this->search}%")
                            ->orWhere('last_name', 'like', "%{$this->search}%")
                            ->orWhere('email', 'like', "%{$this->search}%")
                            ->orWhere('phone', 'like', "%{$this->search}%")));
            }))
            ->when($this->status === 'available', fn ($query) => $query->where('is_active', true)->where('balance', '>', 0)->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>=', $now)))
            ->when($this->status === 'expired', fn ($query) => $query->where('is_active', true)->whereNotNull('expires_at')->where('expires_at', '<', $now))
            ->when($this->status === 'depleted', fn ($query) => $query->where('is_active', true)->where('balance', '<=', 0)->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>=', $now)))
            ->when($this->status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->latest()
            ->paginate(20);

        return view('livewire.giftcards.index', ['giftcards' => $giftcards, 'stats' => $stats]);
    }
}
