<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Item;
use App\Domain\Identity\Models\User;
use App\Domain\Promotions\Models\Promotion;
use App\Livewire\Promotions\Form as PromotionForm;
use App\Livewire\Promotions\Index as PromotionsIndex;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed();
    $this->admin = User::where('username', 'admin')->firstOrFail();
    $this->cashier = User::where('username', 'cashier')->firstOrFail();
    $this->noAbilities = User::factory()->create();
    $this->item = Item::where('sku', 'BEV-COLA-330')->firstOrFail();
});

it('denies promotions access to a user without the promotions ability', function () {
    $this->actingAs($this->noAbilities)->get(route('promotions.index'))->assertForbidden();
});

it('lets a Cashier view promotions but not manage them', function () {
    $this->actingAs($this->cashier)->get(route('promotions.index'))->assertOk();
    $this->actingAs($this->cashier)->get(route('promotions.create'))->assertForbidden();
});

it('creates a promotion with a condition through the Livewire form', function () {
    $this->actingAs($this->admin)->get(route('promotions.create'))->assertOk();

    Livewire::actingAs($this->admin)
        ->test(PromotionForm::class)
        ->set('name', '10% off cola')
        ->set('reward_type', Promotion::REWARD_PERCENT_OFF)
        ->set('reward_value', '10')
        ->set('conditions', [['subject' => 'item', 'operator' => 'in', 'value' => (string) $this->item->id]])
        ->call('save')
        ->assertHasNoErrors();

    $promotion = Promotion::where('name', '10% off cola')->first();

    expect($promotion)->not->toBeNull()
        ->and($promotion->conditions)->toHaveCount(1)
        ->and($promotion->conditions->first()->value)->toBe([$this->item->id]);
});

it('stores starts_at as UTC converted from the store-local time entered in the form', function () {
    // config('pos.timezone') is Asia/Colombo (+05:30) in this test env. An
    // admin typing "2026-09-21 01:01" into the datetime-local field means
    // their own local wall clock, not UTC -- prior to this fix the raw
    // string was stored as literal UTC, silently pushing the promotion's
    // real start 5.5 hours later than intended (and, on re-edit, redisplaying
    // that same UTC value as if it were local, making it look like it had
    // already started when it hadn't).
    Livewire::actingAs($this->admin)
        ->test(PromotionForm::class)
        ->set('name', 'Local time promo')
        ->set('conditions', [['subject' => 'item', 'operator' => 'in', 'value' => (string) $this->item->id]])
        ->set('starts_at', '2026-09-21T01:01')
        ->call('save')
        ->assertHasNoErrors();

    $promotion = Promotion::where('name', 'Local time promo')->firstOrFail();

    expect($promotion->starts_at->timezone('UTC')->toDateTimeString())->toBe('2026-09-20 19:31:00');
});

it('redisplays starts_at converted back to store-local time when reopening the form', function () {
    $promotion = Promotion::create([
        'name' => 'Redisplay check',
        'reward_type' => Promotion::REWARD_PERCENT_OFF,
        'reward_value' => '10',
        'is_active' => true,
        'starts_at' => '2026-09-20 19:31:00', // UTC; local (Asia/Colombo) is 2026-09-21 01:01
    ])->refresh();
    $promotion->conditions()->create(['subject' => 'item', 'operator' => 'in', 'value' => [$this->item->id]]);

    Livewire::actingAs($this->admin)
        ->test(PromotionForm::class, ['promotion' => $promotion])
        ->assertSet('starts_at', '2026-09-21T01:01');
});

it('creates a promotion with an item condition set as a multi-select array, like the picker sends', function () {
    $croissant = Item::where('sku', 'BAK-CROIS')->firstOrFail();

    Livewire::actingAs($this->admin)
        ->test(PromotionForm::class)
        ->set('name', 'Bakery bundle')
        ->set('reward_type', Promotion::REWARD_PERCENT_OFF)
        ->set('reward_value', '10')
        ->set('conditions', [['subject' => 'item', 'operator' => 'in', 'value' => [(string) $this->item->id, (string) $croissant->id]]])
        ->call('save')
        ->assertHasNoErrors();

    $promotion = Promotion::where('name', 'Bakery bundle')->firstOrFail();
    expect($promotion->conditions->first()->value)->toBe([$this->item->id, $croissant->id]);
});

it('resets a condition row operator and value when its subject changes', function () {
    Livewire::actingAs($this->admin)
        ->test(PromotionForm::class)
        ->set('conditions.0.subject', 'item')
        ->set('conditions.0.operator', 'in')
        ->set('conditions.0.value', [(string) $this->item->id])
        ->set('conditions.0.subject', 'cart_total')
        ->assertSet('conditions.0.operator', 'gte')
        ->assertSet('conditions.0.value', '');
});

it('rejects an operator that does not apply to the condition subject', function () {
    Livewire::actingAs($this->admin)
        ->test(PromotionForm::class)
        ->set('name', 'Bad operator')
        ->set('conditions', [['subject' => 'cart_total', 'operator' => 'in', 'value' => '50']])
        ->call('save')
        ->assertHasErrors('conditions.0.operator');
});

it('rejects a non-numeric value on a cart_total condition', function () {
    Livewire::actingAs($this->admin)
        ->test(PromotionForm::class)
        ->set('name', 'Bad threshold')
        ->set('conditions', [['subject' => 'cart_total', 'operator' => 'gte', 'value' => 'a lot']])
        ->call('save')
        ->assertHasErrors('conditions.0.value');
});

it('rejects saving a promotion with no conditions', function () {
    Livewire::actingAs($this->admin)
        ->test(PromotionForm::class)
        ->set('name', 'No conditions')
        ->set('conditions', [])
        ->call('save')
        ->assertHasErrors('conditions');
});

it('rejects saving a bogo promotion with no quantity condition', function () {
    Livewire::actingAs($this->admin)
        ->test(PromotionForm::class)
        ->set('name', 'Bogo without threshold')
        ->set('reward_type', Promotion::REWARD_BOGO)
        ->set('conditions', [['subject' => 'item', 'operator' => 'in', 'value' => (string) $this->item->id]])
        ->call('save')
        ->assertHasErrors('conditions');
});

it('rejects saving a free_item promotion with no reward item', function () {
    Livewire::actingAs($this->admin)
        ->test(PromotionForm::class)
        ->set('name', 'Free item without reward')
        ->set('reward_type', Promotion::REWARD_FREE_ITEM)
        ->set('conditions', [['subject' => 'item', 'operator' => 'in', 'value' => (string) $this->item->id]])
        ->call('save')
        ->assertHasErrors('conditions');
});

it('saves a valid bogo promotion through the Livewire form', function () {
    Livewire::actingAs($this->admin)
        ->test(PromotionForm::class)
        ->set('name', 'Bogo beverages')
        ->set('reward_type', Promotion::REWARD_BOGO)
        ->set('conditions', [
            ['subject' => 'item', 'operator' => 'in', 'value' => (string) $this->item->id],
            ['subject' => 'quantity', 'operator' => 'gte', 'value' => '2'],
        ])
        ->call('save')
        ->assertHasNoErrors();

    $promotion = Promotion::where('name', 'Bogo beverages')->firstOrFail();
    expect($promotion->reward_type)->toBe(Promotion::REWARD_BOGO)
        ->and($promotion->conditions)->toHaveCount(2);
});

it('saves a valid free_item promotion through the Livewire form', function () {
    $croissant = Item::where('sku', 'BAK-CROIS')->firstOrFail();

    Livewire::actingAs($this->admin)
        ->test(PromotionForm::class)
        ->set('name', 'Free croissant')
        ->set('reward_type', Promotion::REWARD_FREE_ITEM)
        ->set('reward_item_id', $croissant->id)
        ->set('conditions', [['subject' => 'item', 'operator' => 'in', 'value' => (string) $this->item->id]])
        ->call('save')
        ->assertHasNoErrors();

    $promotion = Promotion::where('name', 'Free croissant')->firstOrFail();
    expect($promotion->reward_type)->toBe(Promotion::REWARD_FREE_ITEM)
        ->and($promotion->reward_item_id)->toBe($croissant->id);
});

it('generates a coupon for an existing promotion', function () {
    $promotion = Promotion::create([
        'name' => 'Coupon promo',
        'reward_type' => Promotion::REWARD_PERCENT_OFF,
        'reward_value' => '10',
        'requires_coupon' => true,
        'is_active' => true,
    ]);
    $promotion->conditions()->create(['subject' => 'item', 'operator' => 'in', 'value' => [$this->item->id]]);

    Livewire::actingAs($this->admin)
        ->test(PromotionForm::class, ['promotion' => $promotion->fresh()])
        ->set('newCoupon.code', 'WELCOME10')
        ->set('newCoupon.max_uses', '5')
        ->call('generateCoupon')
        ->assertHasNoErrors();

    expect($promotion->coupons()->where('code', 'WELCOME10')->exists())->toBeTrue();
});

it('denies updating a promotion to a user without promotions.manage', function () {
    $promotion = Promotion::create([
        'name' => 'Locked',
        'reward_type' => Promotion::REWARD_PERCENT_OFF,
        'reward_value' => '10',
        'is_active' => true,
    ])->refresh();
    $promotion->conditions()->create(['subject' => 'item', 'operator' => 'in', 'value' => [$this->item->id]]);

    // A Cashier has promotions.view (can open the form to look, since Register
    // fast-follows added read-only promotion browsing) but not promotions.manage.
    Livewire::actingAs($this->cashier)
        ->test(PromotionForm::class, ['promotion' => $promotion])
        ->set('name', 'Attempted change')
        ->call('save')
        ->assertForbidden();
});

it('lets an admin delete a promotion', function () {
    $promotion = Promotion::create([
        'name' => 'To delete',
        'reward_type' => Promotion::REWARD_PERCENT_OFF,
        'reward_value' => '10',
        'is_active' => true,
    ]);

    Livewire::actingAs($this->admin)
        ->test(PromotionsIndex::class)
        ->call('delete', $promotion->id);

    expect(Promotion::find($promotion->id))->toBeNull()
        ->and(Promotion::withTrashed()->find($promotion->id))->not->toBeNull();
});

it('denies deleting a promotion to a Cashier', function () {
    $promotion = Promotion::create([
        'name' => 'Guarded',
        'reward_type' => Promotion::REWARD_PERCENT_OFF,
        'reward_value' => '10',
        'is_active' => true,
    ]);

    Livewire::actingAs($this->cashier)
        ->test(PromotionsIndex::class)
        ->call('delete', $promotion->id)
        ->assertForbidden();
});
