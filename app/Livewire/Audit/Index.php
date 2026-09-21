<?php

declare(strict_types=1);

namespace App\Livewire\Audit;

use App\Domain\Catalog\Models\Item;
use App\Domain\Identity\Models\User;
use App\Domain\Purchasing\Models\SupplierInvoice;
use App\Domain\Sales\Models\Sale;
use App\Domain\Sales\Models\Shift;
use Carbon\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Activitylog\Models\Activity;

#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    public string $from;

    public string $to;

    public ?int $causerId = null;

    public ?string $event = null;

    public ?int $expandedActivityId = null;

    public function mount(): void
    {
        $this->from = now()->subDays(30)->toDateString();
        $this->to = now()->toDateString();
    }

    public function updatingFrom(): void
    {
        $this->resetPage();
    }

    public function updatingTo(): void
    {
        $this->resetPage();
    }

    public function updatingCauserId(): void
    {
        $this->resetPage();
    }

    public function updatingEvent(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->from = now()->subDays(30)->toDateString();
        $this->to = now()->toDateString();
        $this->causerId = null;
        $this->event = null;
        $this->expandedActivityId = null;
        $this->resetPage();
    }

    public function toggle(int $activityId): void
    {
        $this->expandedActivityId = $this->expandedActivityId === $activityId ? null : $activityId;
    }

    /**
     * Attribute values in `attribute_changes` are whatever the model's own
     * casts produced when the package serialized them for storage -- a
     * Money-cast field (e.g. Sale's `total`) comes through as
     * `{"amount": "150.00", "currency": "LKR"}` rather than a plain
     * scalar, so this can't just be echoed directly in the view.
     */
    public function formatChangeValue(mixed $value): string
    {
        if ($value === null) {
            return '—';
        }

        if (is_array($value) && isset($value['amount'], $value['currency'])) {
            return "{$value['currency']} {$value['amount']}";
        }

        if (is_array($value)) {
            return json_encode($value) ?: '—';
        }

        return (string) $value;
    }

    public function subjectLabel(Activity $activity): string
    {
        if ($activity->subject_type === null) {
            return '—';
        }

        return match ($activity->subject_type) {
            Sale::class => 'Sale #'.($activity->subject?->number ?? $activity->subject_id),
            SupplierInvoice::class => 'Supplier Invoice #'.($activity->subject?->invoice_number ?? $activity->subject_id),
            Item::class => $activity->subject?->name ?? ('Item #'.$activity->subject_id),
            Shift::class => 'Shift #'.$activity->subject_id.($activity->subject?->terminal?->name ? " ({$activity->subject->terminal->name})" : ''),
            default => class_basename($activity->subject_type).' #'.$activity->subject_id,
        };
    }

    public function render()
    {
        Gate::authorize('audit.view');

        $from = Carbon::parse($this->from)->startOfDay();
        $to = Carbon::parse($this->to)->endOfDay();

        $activities = Activity::query()
            ->with(['causer', 'subject'])
            ->whereBetween('created_at', [$from, $to])
            ->when($this->causerId, fn ($q) => $q->where('causer_id', $this->causerId)->where('causer_type', User::class))
            ->when($this->event, fn ($q) => $q->where('event', $this->event))
            ->latest()
            ->paginate(20);

        return view('livewire.audit.index', [
            'activities' => $activities,
            'causers' => User::query()->with('person')->orderBy('username')->get(),
        ]);
    }
}
