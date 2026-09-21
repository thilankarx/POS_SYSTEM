<?php

declare(strict_types=1);

namespace App\Livewire\Promotions;

use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Item;
use App\Domain\Crm\Models\Customer;
use App\Domain\Loyalty\Models\LoyaltyPackage;
use App\Domain\Promotions\Actions\CreatePromotionAction;
use App\Domain\Promotions\Actions\GenerateCouponAction;
use App\Domain\Promotions\Actions\UpdatePromotionAction;
use App\Domain\Promotions\Exceptions\PromotionException;
use App\Domain\Promotions\Models\Promotion;
use App\Domain\Promotions\Models\PromotionCondition;
use App\Support\Logging\DomainLog;
use App\Support\Money\Rules\ValidMoneyAmount;
use Carbon\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Form extends Component
{
    public ?Promotion $promotion = null;

    public string $name = '';

    public string $code = '';

    public string $description = '';

    public string $reward_type = Promotion::REWARD_PERCENT_OFF;

    public string $reward_value = '0';

    public ?int $reward_item_id = null;

    public bool $requires_coupon = false;

    public bool $stackable = false;

    public string $priority = '0';

    public ?string $max_redemptions = null;

    public ?string $max_redemptions_per_customer = null;

    public ?string $starts_at = null;

    public ?string $ends_at = null;

    /** @var array<int, string> */
    public array $active_days = [];

    public ?string $active_from = null;

    public ?string $active_to = null;

    public bool $is_active = true;

    /** @var array<int, array{subject: string, operator: string, value: string}> */
    public array $conditions = [];

    /** @var array{code: string, customer_id: ?int, max_uses: string, expires_at: ?string} */
    public array $newCoupon = ['code' => '', 'customer_id' => null, 'max_uses' => '1', 'expires_at' => null];

    public function mount(?Promotion $promotion = null): void
    {
        $this->promotion = $promotion;

        if ($promotion !== null) {
            Gate::authorize('view', $promotion);

            $this->name = $promotion->name;
            $this->code = (string) $promotion->code;
            $this->description = (string) $promotion->description;
            $this->reward_type = $promotion->reward_type;
            $this->reward_value = (string) $promotion->reward_value->getAmount();
            $this->reward_item_id = $promotion->reward_item_id;
            $this->requires_coupon = $promotion->requires_coupon;
            $this->stackable = $promotion->stackable;
            $this->priority = (string) $promotion->priority;
            $this->max_redemptions = $promotion->max_redemptions !== null ? (string) $promotion->max_redemptions : null;
            $this->max_redemptions_per_customer = $promotion->max_redemptions_per_customer !== null ? (string) $promotion->max_redemptions_per_customer : null;
            $this->starts_at = $this->toLocalDateTimeInput($promotion->starts_at);
            $this->ends_at = $this->toLocalDateTimeInput($promotion->ends_at);
            $this->active_days = $promotion->active_days !== null ? explode(',', $promotion->active_days) : [];
            $this->active_from = $promotion->active_from?->format('H:i');
            $this->active_to = $promotion->active_to?->format('H:i');
            $this->is_active = $promotion->is_active;
            $this->conditions = $promotion->conditions->map(fn (PromotionCondition $condition) => [
                'subject' => $condition->subject,
                'operator' => $condition->operator,
                'value' => $this->isListSubject($condition->subject)
                    ? array_map('strval', is_array($condition->value) ? $condition->value : [$condition->value])
                    : (is_array($condition->value) ? (string) ($condition->value[0] ?? '') : (string) $condition->value),
            ])->all();
        } else {
            Gate::authorize('create', Promotion::class);
            $this->addCondition();
        }
    }

    public function updatedRewardType(): void
    {
        if (in_array($this->reward_type, [Promotion::REWARD_BOGO, Promotion::REWARD_FREE_ITEM], true)) {
            $this->reward_value = '0';
        }
    }

    public function addCondition(): void
    {
        $this->conditions[] = ['subject' => PromotionCondition::SUBJECT_ITEM, 'operator' => PromotionCondition::OPERATOR_IN, 'value' => []];
    }

    public function removeCondition(int $index): void
    {
        unset($this->conditions[$index]);
        $this->conditions = array_values($this->conditions);
    }

    /**
     * starts_at/ends_at are stored as absolute UTC instants, but the
     * datetime-local input has no timezone of its own -- it's a bare wall
     * clock string. Displaying the raw UTC value there (as this used to do)
     * shows the store's admin a time that looks shifted by their own
     * UTC offset from what they actually set, which reads as "already
     * started" or "way off" depending on the direction. Converting to
     * config('pos.timezone') here is the read-side half of that fix; save()
     * does the write-side half.
     */
    private function toLocalDateTimeInput(?\Carbon\CarbonInterface $utc): ?string
    {
        return $utc?->copy()->timezone(config('pos.timezone'))->format('Y-m-d\TH:i');
    }

    /**
     * The mirror of toLocalDateTimeInput(): the datetime-local input's value
     * is the store's local wall clock (config('pos.timezone')), so it has to
     * be interpreted as that timezone -- not the app's UTC default -- before
     * it's converted to UTC for storage.
     */
    private function toUtcDateTime(?string $local): ?string
    {
        if ($local === null || trim($local) === '') {
            return null;
        }

        return Carbon::parse($local, config('pos.timezone'))->timezone('UTC')->toDateTimeString();
    }

    /**
     * A list-subject condition (item/category/customer group) is picked from
     * a checklist and only ever means "matches one of these" -- gte/lte/eq
     * mean nothing there. A scalar-subject condition (cart total/quantity)
     * is a single number and only ever compares with gte/lte/eq -- in/not_in
     * mean nothing there. PromotionEngine enforces this split already
     * (matchesItemOrCategory ignores gte/lte/eq entirely; matchesCartTotal/
     * matchesQuantity's `default => false` makes in/not_in silently never
     * match) -- this keeps the form from ever offering a combination the
     * engine can't actually evaluate.
     */
    private function isListSubject(string $subject): bool
    {
        return in_array($subject, [
            PromotionCondition::SUBJECT_ITEM,
            PromotionCondition::SUBJECT_CATEGORY,
            PromotionCondition::SUBJECT_CUSTOMER_GROUP,
        ], true);
    }

    public function updatedConditions(mixed $value, ?string $name = null): void
    {
        // Livewire calls this with $name === null when the whole `conditions`
        // array is replaced in one go (::set('conditions', [...]), as tests
        // and any future bulk-assignment caller do) rather than one nested
        // path at a time -- only a per-row `{index}.subject` change should
        // reset that row's operator/value.
        if ($name === null || ! str_ends_with($name, '.subject')) {
            return;
        }

        $index = (int) explode('.', $name)[0];
        $isListSubject = $this->isListSubject($value);

        $this->conditions[$index]['operator'] = $isListSubject ? PromotionCondition::OPERATOR_IN : PromotionCondition::OPERATOR_GTE;
        $this->conditions[$index]['value'] = $isListSubject ? [] : '';
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:40', Rule::unique('promotions', 'code')->ignore($this->promotion)],
            'description' => ['nullable', 'string'],
            'reward_type' => ['required', Rule::in([
                Promotion::REWARD_PERCENT_OFF,
                Promotion::REWARD_AMOUNT_OFF,
                Promotion::REWARD_FIXED_PRICE,
                Promotion::REWARD_BOGO,
                Promotion::REWARD_FREE_ITEM,
            ])],
            'reward_value' => ['required', new ValidMoneyAmount],
            'reward_item_id' => ['nullable', Rule::exists('items', 'id')],
            'requires_coupon' => ['boolean'],
            'stackable' => ['boolean'],
            'priority' => ['required', 'integer', 'min:0'],
            'max_redemptions' => ['nullable', 'integer', 'min:1'],
            'max_redemptions_per_customer' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'active_days' => ['array'],
            'active_days.*' => ['string', 'in:1,2,3,4,5,6,7'],
            'active_from' => ['nullable', 'date_format:H:i'],
            'active_to' => ['nullable', 'date_format:H:i'],
            'is_active' => ['boolean'],
            'conditions' => ['required', 'array', 'min:1'],
            'conditions.*.subject' => ['required', Rule::in([
                PromotionCondition::SUBJECT_ITEM,
                PromotionCondition::SUBJECT_CATEGORY,
                PromotionCondition::SUBJECT_CUSTOMER_GROUP,
                PromotionCondition::SUBJECT_CART_TOTAL,
                PromotionCondition::SUBJECT_QUANTITY,
            ])],
            'conditions.*.operator' => ['required', Rule::in([
                PromotionCondition::OPERATOR_IN,
                PromotionCondition::OPERATOR_NOT_IN,
                PromotionCondition::OPERATOR_GTE,
                PromotionCondition::OPERATOR_LTE,
                PromotionCondition::OPERATOR_EQ,
            ])],
            'conditions.*.value' => ['required'],
        ];
    }

    private function validateConditionShapes($validator): void
    {
        foreach ($this->conditions as $index => $condition) {
            if (! isset($condition['subject'])) {
                continue;
            }

            $isListSubject = $this->isListSubject($condition['subject']);
            $validOperators = $isListSubject
                ? [PromotionCondition::OPERATOR_IN, PromotionCondition::OPERATOR_NOT_IN]
                : [PromotionCondition::OPERATOR_GTE, PromotionCondition::OPERATOR_LTE, PromotionCondition::OPERATOR_EQ];

            if (isset($condition['operator']) && ! in_array($condition['operator'], $validOperators, true)) {
                $validator->errors()->add("conditions.{$index}.operator", 'This operator does not apply to this condition type.');
            }

            if ($isListSubject) {
                // The picker sends an array of selected ids, but a caller
                // setting this programmatically (API, tests) may still pass
                // the older comma-separated-string form -- both are valid
                // input to parseConditionValue(), so both must pass here.
                $hasValue = is_array($condition['value'])
                    ? $condition['value'] !== []
                    : trim((string) $condition['value']) !== '';

                if (! $hasValue) {
                    $validator->errors()->add("conditions.{$index}.value", 'Select at least one option.');
                }
            } elseif (is_array($condition['value']) || ! is_numeric($condition['value'])) {
                $validator->errors()->add("conditions.{$index}.value", 'Enter a number.');
            }
        }
    }

    public function save(): void
    {
        $this->withValidator(function ($validator) {
            $validator->after(fn ($validator) => $this->validateConditionShapes($validator));
        });

        $validated = $this->validate();

        $attributes = [
            'name' => $validated['name'],
            'code' => $validated['code'] ?: null,
            'description' => $validated['description'] ?: null,
            'reward_type' => $validated['reward_type'],
            'reward_value' => $validated['reward_value'],
            'reward_item_id' => $validated['reward_item_id'] ?: null,
            'requires_coupon' => $validated['requires_coupon'],
            'stackable' => $validated['stackable'],
            'priority' => $validated['priority'],
            'max_redemptions' => $validated['max_redemptions'] ?: null,
            'max_redemptions_per_customer' => $validated['max_redemptions_per_customer'] ?: null,
            'starts_at' => $this->toUtcDateTime($validated['starts_at']),
            'ends_at' => $this->toUtcDateTime($validated['ends_at']),
            'active_days' => $validated['active_days'] !== [] ? implode(',', $validated['active_days']) : null,
            'active_from' => $validated['active_from'] ?: null,
            'active_to' => $validated['active_to'] ?: null,
            'is_active' => $validated['is_active'],
        ];

        $conditions = array_map(fn (array $row) => [
            'subject' => $row['subject'],
            'operator' => $row['operator'],
            'value' => $this->parseConditionValue($row['subject'], $row['value']),
        ], $validated['conditions']);

        try {
            if ($this->promotion !== null) {
                Gate::authorize('update', $this->promotion);
                $this->promotion = app(UpdatePromotionAction::class)->execute($this->promotion, $attributes, $conditions);
            } else {
                Gate::authorize('create', Promotion::class);
                $this->promotion = app(CreatePromotionAction::class)->execute($attributes, $conditions);
            }
        } catch (PromotionException $e) {
            DomainLog::refused($e, ['promotion_id' => $this->promotion?->id]);
            $this->addError('conditions', $e->getMessage());

            return;
        }

        session()->flash('status', 'Promotion saved.');
        $this->redirectRoute('promotions.edit', $this->promotion);
    }

    public function generateCoupon(): void
    {
        Gate::authorize('update', $this->promotion);

        $validated = $this->validate([
            'newCoupon.code' => ['required', 'string', 'max:64'],
            'newCoupon.customer_id' => ['nullable', Rule::exists('customers', 'id')],
            'newCoupon.max_uses' => ['required', 'integer', 'min:1'],
            'newCoupon.expires_at' => ['nullable', 'date'],
        ]);

        try {
            app(GenerateCouponAction::class)->execute($this->promotion, $validated['newCoupon']);
        } catch (PromotionException $e) {
            DomainLog::refused($e, ['promotion_id' => $this->promotion?->id]);
            $this->addError('newCoupon.code', $e->getMessage());

            return;
        }

        $this->newCoupon = ['code' => '', 'customer_id' => null, 'max_uses' => '1', 'expires_at' => null];
        $this->promotion->refresh();
        session()->flash('status', 'Coupon generated.');
    }

    /** @return array<int, int>|string */
    private function parseConditionValue(string $subject, array|string $raw): array|string
    {
        if ($this->isListSubject($subject)) {
            $parts = is_array($raw) ? $raw : explode(',', $raw);

            return array_values(array_filter(array_map(
                fn (string $part) => (int) trim($part),
                $parts
            ), fn (int $id) => $id !== 0));
        }

        return trim((string) $raw);
    }

    public function render()
    {
        return view('livewire.promotions.form', [
            'items' => Item::active()->orderBy('name')->get(),
            'categories' => Category::orderBy('name')->get(),
            'loyaltyPackages' => LoyaltyPackage::orderBy('name')->get(),
            'customers' => Customer::orderBy('company_name')->limit(200)->get(),
        ]);
    }
}
