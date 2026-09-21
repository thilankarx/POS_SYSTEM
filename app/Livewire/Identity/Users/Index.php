<?php

declare(strict_types=1);

namespace App\Livewire\Identity\Users;

use App\Domain\Identity\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function delete(int $id): void
    {
        $target = User::findOrFail($id);

        Gate::authorize('delete', $target);

        if (! app(UserPolicy::class)->canRemoveOwnerRole(auth()->user(), $target)) {
            session()->flash('error', 'You cannot remove the last active Owner.');

            return;
        }

        $target->delete();

        session()->flash('status', 'User deleted.');
    }

    public function render()
    {
        Gate::authorize('viewAny', User::class);

        $users = User::query()
            ->with(['person', 'roles'])
            ->when($this->search !== '', function ($q) {
                $term = trim($this->search);

                $q->where(function ($query) use ($term) {
                    $query->where('username', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%")
                        ->orWhereHas('person', function ($person) use ($term) {
                            $person->where('first_name', 'like', "%{$term}%")
                                ->orWhere('last_name', 'like', "%{$term}%");
                        });
                });
            })
            ->orderBy('username')
            ->paginate(20);

        return view('livewire.identity.users.index', [
            'users' => $users,
            'totalUsers' => User::count(),
            'activeUsers' => User::where('is_active', true)->count(),
            'inactiveUsers' => User::where('is_active', false)->count(),
        ]);
    }
}
