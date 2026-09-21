<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Domain\Documents\Models\DocumentSequence;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Numbering extends Component
{
    public const KEYS = [
        'sale' => 'Sale',
        'invoice' => 'Invoice',
        'quote' => 'Quote',
        'work_order' => 'Work order',
        'receiving' => 'Receiving',
        'purchase_order' => 'Purchase order',
        'stock_count' => 'Stock count',
        'gift_card' => 'Gift card',
    ];

    /** @var array<string, array{prefix: string, padding: int, next_value: int}> */
    public array $sequences = [];

    public function mount(): void
    {
        Gate::authorize('config.manage');

        foreach (self::KEYS as $key => $label) {
            $config = config("pos.documents.sequences.{$key}", []);
            $sequence = DocumentSequence::query()
                ->where('key', $key)
                ->where('scope', 'global')
                ->first();

            $this->sequences[$key] = [
                'prefix' => $sequence->prefix ?? ($config['prefix'] ?? strtoupper(substr($key, 0, 3))),
                'padding' => $sequence->padding ?? ($config['padding'] ?? 6),
                'next_value' => $sequence->next_value ?? 1,
            ];
        }
    }

    public function rules(): array
    {
        $rules = [];

        foreach (self::KEYS as $key => $label) {
            $rules["sequences.{$key}.prefix"] = ['required', 'string', 'max:32'];
            $rules["sequences.{$key}.padding"] = ['required', 'integer', 'min:1', 'max:10'];
            $rules["sequences.{$key}.next_value"] = ['required', 'integer', 'min:1'];
        }

        return $rules;
    }

    public function save(): void
    {
        Gate::authorize('config.manage');

        $validated = $this->validate();

        DB::transaction(function () use ($validated) {
            foreach ($validated['sequences'] as $key => $values) {
                DocumentSequence::query()->updateOrCreate(
                    ['key' => $key, 'scope' => 'global'],
                    $values,
                );
            }
        });

        session()->flash('status', 'Document numbering saved.');
    }

    public function render()
    {
        return view('livewire.settings.numbering');
    }
}
