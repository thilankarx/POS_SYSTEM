<?php

declare(strict_types=1);

namespace App\Livewire\Identity\Users;

use App\Domain\Identity\Models\Person;
use App\Domain\Identity\Models\User;
use App\Domain\Inventory\Models\StockLocation;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Spatie\Permission\Models\Role;

#[Layout('layouts.app')]
class Form extends Component
{
    public ?User $user = null;

    public string $first_name = '';

    public string $last_name = '';

    public string $phone = '';

    public string $username = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public bool $is_active = true;

    public string $role = '';

    public ?string $commission_rate = null;

    /** @var list<string> */
    public array $stock_location_ids = [];

    public function mount(?User $user = null): void
    {
        $this->user = $user;

        if ($user !== null) {
            Gate::authorize('update', $user);

            $user->loadMissing('person', 'roles');
            $this->first_name = $user->person->first_name;
            $this->last_name = $user->person->last_name;
            $this->phone = (string) $user->person->phone;
            $this->username = $user->username;
            $this->email = $user->email;
            $this->is_active = $user->is_active;
            $this->role = (string) $user->roles->first()?->name;
            $this->commission_rate = $user->commission_rate;
            $this->stock_location_ids = $user->stockLocations()->pluck('stock_locations.id')
                ->map(fn (int $id): string => (string) $id)->all();
        } else {
            Gate::authorize('create', User::class);
        }
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', Rule::unique('users', 'username')->ignore($this->user?->id)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->user?->id)],
            'password' => [$this->user === null ? 'required' : 'nullable', 'confirmed', 'min:12'],
            'is_active' => ['boolean'],
            'role' => ['required', Rule::exists('roles', 'name')->where('guard_name', 'web')],
            'commission_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'stock_location_ids' => ['array'],
            'stock_location_ids.*' => [Rule::exists('stock_locations', 'id')],
        ];
    }

    public function save(): void
    {
        $validated = $this->validate();

        if ($this->user !== null && ! app(UserPolicy::class)->canRemoveOwnerRole(auth()->user(), $this->user)) {
            $wouldLoseOwner = $validated['role'] !== 'Owner' || ! $validated['is_active'];

            if ($wouldLoseOwner) {
                $this->addError('role', 'You cannot remove the last active Owner.');

                return;
            }
        }

        $personFields = collect($validated)->only(['first_name', 'last_name', 'phone'])->all();
        $userFields = [
            'username' => $validated['username'],
            'email' => $validated['email'],
            'is_active' => $validated['is_active'],
            'commission_rate' => $validated['commission_rate'] ?: null,
        ];

        if (! empty($validated['password'])) {
            $userFields['password'] = $validated['password'];
        }

        if ($this->user !== null) {
            Gate::authorize('update', $this->user);
            $this->user->person->update($personFields);
            $this->user->update($userFields);
        } else {
            Gate::authorize('create', User::class);
            $person = Person::create($personFields);
            $this->user = User::create([...$userFields, 'person_id' => $person->id, 'email_verified_at' => now()]);
        }

        $previousRole = $this->user->roles->pluck('name')->first();

        $this->user->syncRoles([$validated['role']]);
        $this->user->stockLocations()->sync($validated['stock_location_ids'] ?? []);

        // syncRoles() writes to the role_has_permissions pivot table, not a
        // column on $this->user, so LogsActivity's dirty-attribute tracking
        // on the User model never sees it -- log it explicitly, the one
        // place a role is ever changed.
        if ($previousRole !== $validated['role']) {
            activity()
                ->causedBy(auth()->user())
                ->performedOn($this->user)
                ->withProperties(['from' => $previousRole, 'to' => $validated['role']])
                ->log('role changed');
        }

        session()->flash('status', 'User saved.');
        $this->redirectRoute('users.index');
    }

    public function render()
    {
        return view('livewire.identity.users.form', [
            'roles' => Role::where('guard_name', 'web')->orderBy('name')->get(),
            'stockLocations' => StockLocation::orderBy('name')->get(),
        ]);
    }
}
