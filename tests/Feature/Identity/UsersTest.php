<?php

declare(strict_types=1);

use App\Domain\Identity\Models\User;
use App\Domain\Inventory\Models\StockLocation;
use App\Livewire\Identity\Users\Form;
use App\Livewire\Identity\Users\Index;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->seed();
    $this->owner = User::where('username', 'admin')->firstOrFail();
    $this->manager = User::factory()->create();
    $this->manager->assignRole('Manager');
});

it('lists and creates users', function () {
    $this->actingAs($this->owner)
        ->get(route('users.index'))
        ->assertOk();

    Livewire::actingAs($this->owner)
        ->test(Form::class)
        ->set('first_name', 'New')
        ->set('last_name', 'Hire')
        ->set('username', 'newhire')
        ->set('email', 'newhire@example.test')
        ->set('password', 'a-very-long-password')
        ->set('password_confirmation', 'a-very-long-password')
        ->set('role', 'Cashier')
        ->call('save')
        ->assertRedirect(route('users.index'));

    $created = User::where('username', 'newhire')->firstOrFail();
    expect($created->hasRole('Cashier'))->toBeTrue();
});

it('logs a role change with the acting user as causer', function () {
    $cashier = User::where('username', 'cashier')->firstOrFail();

    Livewire::actingAs($this->owner)
        ->test(Form::class, ['user' => $cashier])
        ->set('role', 'Manager')
        ->call('save')
        ->assertRedirect(route('users.index'));

    $activity = Activity::where('description', 'role changed')->latest('id')->first();

    expect($cashier->fresh()->hasRole('Manager'))->toBeTrue()
        ->and($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($this->owner->id)
        ->and($activity->subject_id)->toBe($cashier->id)
        ->and($activity->properties->get('from'))->toBe('Cashier')
        ->and($activity->properties->get('to'))->toBe('Manager');
});

it('does not log a role change when the role is left the same', function () {
    $cashier = User::where('username', 'cashier')->firstOrFail();

    Livewire::actingAs($this->owner)
        ->test(Form::class, ['user' => $cashier])
        ->set('phone', '555-1234')
        ->call('save')
        ->assertRedirect(route('users.index'));

    expect(Activity::where('description', 'role changed')->where('subject_id', $cashier->id)->exists())->toBeFalse();
});

it('logs deactivating a user via LogsActivity on the User model', function () {
    $cashier = User::where('username', 'cashier')->firstOrFail();

    Livewire::actingAs($this->owner)
        ->test(Form::class, ['user' => $cashier])
        ->set('is_active', false)
        ->call('save')
        ->assertRedirect(route('users.index'));

    $activity = Activity::where('subject_type', User::class)->where('subject_id', $cashier->id)->latest('id')->first();

    expect($cashier->fresh()->is_active)->toBeFalse()
        ->and($activity)->not->toBeNull()
        ->and($activity->attribute_changes->get('attributes')['is_active'])->toBeFalse();
});

it('edits a user without changing the password when left blank', function () {
    $cashier = User::where('username', 'cashier')->firstOrFail();
    $originalPassword = $cashier->password;

    Livewire::actingAs($this->owner)
        ->test(Form::class, ['user' => $cashier])
        ->set('phone', '555-9999')
        ->call('save')
        ->assertRedirect(route('users.index'));

    expect($cashier->fresh()->person->phone)->toBe('555-9999')
        ->and($cashier->fresh()->password)->toBe($originalPassword);
});

it('rejects a mismatched password confirmation', function () {
    Livewire::actingAs($this->owner)
        ->test(Form::class)
        ->set('first_name', 'New')
        ->set('last_name', 'Hire')
        ->set('username', 'newhire2')
        ->set('email', 'newhire2@example.test')
        ->set('password', 'a-very-long-password')
        ->set('password_confirmation', 'does-not-match')
        ->set('role', 'Cashier')
        ->call('save')
        ->assertHasErrors('password');
});

it('rejects a duplicate username and email', function () {
    Livewire::actingAs($this->owner)
        ->test(Form::class)
        ->set('first_name', 'New')
        ->set('last_name', 'Hire')
        ->set('username', 'admin')
        ->set('email', 'admin@example.test')
        ->set('password', 'a-very-long-password')
        ->set('password_confirmation', 'a-very-long-password')
        ->set('role', 'Cashier')
        ->call('save')
        ->assertHasErrors(['username', 'email']);
});

it('refuses to remove the Owner role from the last active Owner via the form', function () {
    Livewire::actingAs($this->owner)
        ->test(Form::class, ['user' => $this->owner])
        ->set('role', 'Manager')
        ->call('save')
        ->assertHasErrors('role');

    expect($this->owner->fresh()->hasRole('Owner'))->toBeTrue();
});

it('refuses to deactivate the last active Owner via the form', function () {
    Livewire::actingAs($this->owner)
        ->test(Form::class, ['user' => $this->owner])
        ->set('is_active', false)
        ->call('save')
        ->assertHasErrors('role');

    expect($this->owner->fresh()->is_active)->toBeTrue();
});

it('refuses to delete the last active Owner from the index', function () {
    Livewire::actingAs($this->owner)
        ->test(Index::class)
        ->call('delete', $this->owner->id);

    expect(User::find($this->owner->id))->not->toBeNull();
});

it('allows removing the Owner role when another active Owner remains', function () {
    $secondOwner = User::factory()->create();
    $secondOwner->assignRole('Owner');

    Livewire::actingAs($this->owner)
        ->test(Form::class, ['user' => $this->owner])
        ->set('role', 'Manager')
        ->call('save')
        ->assertRedirect(route('users.index'));

    expect($this->owner->fresh()->hasRole('Owner'))->toBeFalse();
});

it('lets Manager view the users list but not manage users, per the seeded role split', function () {
    $this->actingAs($this->manager)
        ->get(route('users.index'))
        ->assertOk();

    $this->actingAs($this->manager)
        ->get(route('users.create'))
        ->assertForbidden();
});

it('blocks a role with no users.* ability at all from the users section', function () {
    $cashier = User::where('username', 'cashier')->firstOrFail();

    $this->actingAs($cashier)
        ->get(route('users.index'))
        ->assertForbidden();
});

it('saves a commission rate on a user', function () {
    Livewire::actingAs($this->owner)
        ->test(Form::class)
        ->set('first_name', 'New')
        ->set('last_name', 'Waiter')
        ->set('username', 'newwaiter')
        ->set('email', 'newwaiter@example.test')
        ->set('password', 'a-very-long-password')
        ->set('password_confirmation', 'a-very-long-password')
        ->set('role', 'Cashier')
        ->set('commission_rate', '5.5')
        ->call('save')
        ->assertRedirect(route('users.index'));

    $created = User::where('username', 'newwaiter')->firstOrFail();
    expect((string) $created->commission_rate)->toBe('5.50');
});

it('leaves commission_rate null when left blank', function () {
    $cashier = User::where('username', 'cashier')->firstOrFail();

    Livewire::actingAs($this->owner)
        ->test(Form::class, ['user' => $cashier])
        ->set('commission_rate', '')
        ->call('save')
        ->assertRedirect(route('users.index'));

    expect($cashier->fresh()->commission_rate)->toBeNull();
});

it('rejects an out-of-range commission rate', function () {
    Livewire::actingAs($this->owner)
        ->test(Form::class)
        ->set('first_name', 'New')
        ->set('last_name', 'Waiter')
        ->set('username', 'badrate')
        ->set('email', 'badrate@example.test')
        ->set('password', 'a-very-long-password')
        ->set('password_confirmation', 'a-very-long-password')
        ->set('role', 'Cashier')
        ->set('commission_rate', '150')
        ->call('save')
        ->assertHasErrors('commission_rate');
});

it('assigns stock locations to a new user, without which they could never pick a terminal', function () {
    $main = StockLocation::where('code', 'MAIN')->firstOrFail();
    $warehouse = StockLocation::where('code', 'WH')->firstOrFail();

    Livewire::actingAs($this->owner)
        ->test(Form::class)
        ->set('first_name', 'New')
        ->set('last_name', 'Waiter')
        ->set('username', 'newlocationuser')
        ->set('email', 'newlocationuser@example.test')
        ->set('password', 'a-very-long-password')
        ->set('password_confirmation', 'a-very-long-password')
        ->set('role', 'Waiter')
        ->set('stock_location_ids', [(string) $main->id, (string) $warehouse->id])
        ->call('save')
        ->assertRedirect(route('users.index'));

    $created = User::where('username', 'newlocationuser')->firstOrFail();
    expect($created->stockLocations()->pluck('stock_locations.id')->sort()->values()->all())
        ->toBe([$main->id, $warehouse->id]);
});

it('syncs stock locations on an existing user, removing ones that get unchecked', function () {
    $main = StockLocation::where('code', 'MAIN')->firstOrFail();
    $warehouse = StockLocation::where('code', 'WH')->firstOrFail();
    $cashier = User::where('username', 'cashier')->firstOrFail();
    $cashier->stockLocations()->sync([$main->id, $warehouse->id]);

    Livewire::actingAs($this->owner)
        ->test(Form::class, ['user' => $cashier])
        ->set('stock_location_ids', [(string) $warehouse->id])
        ->call('save')
        ->assertRedirect(route('users.index'));

    expect($cashier->fresh()->stockLocations()->pluck('stock_locations.id')->all())
        ->toBe([$warehouse->id]);
});

it('preloads a user\'s current stock locations into the form', function () {
    $main = StockLocation::where('code', 'MAIN')->firstOrFail();
    $cashier = User::where('username', 'cashier')->firstOrFail();
    $cashier->stockLocations()->sync([$main->id]);

    Livewire::actingAs($this->owner)
        ->test(Form::class, ['user' => $cashier])
        ->assertSet('stock_location_ids', [(string) $main->id]);
});
