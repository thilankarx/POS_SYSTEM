<?php

declare(strict_types=1);

namespace App\Livewire\Reporting;

use App\Domain\Reporting\Queries\CommissionReportQuery;
use Carbon\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Commissions extends Component
{
    public string $from;

    public string $to;

    public ?int $expandedWaiterId = null;

    public function mount(): void
    {
        $this->from = now()->startOfMonth()->toDateString();
        $this->to = now()->toDateString();
    }

    public function toggle(int $waiterId): void
    {
        $this->expandedWaiterId = $this->expandedWaiterId === $waiterId ? null : $waiterId;
    }

    public function render()
    {
        Gate::authorize('reports.employees');

        $from = Carbon::parse($this->from)->startOfDay();
        $to = Carbon::parse($this->to)->endOfDay();
        $query = app(CommissionReportQuery::class);

        return view('livewire.reporting.commissions', [
            'waiters' => $query->summary($from, $to),
            'drillDown' => $this->expandedWaiterId ? $query->salesFor($this->expandedWaiterId, $from, $to) : null,
        ]);
    }
}
