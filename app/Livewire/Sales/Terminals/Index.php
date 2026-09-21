<?php

declare(strict_types=1);

namespace App\Livewire\Sales\Terminals;

use App\Domain\Sales\Models\Shift;
use App\Domain\Sales\Models\Terminal;
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
        $terminal = Terminal::findOrFail($id);

        Gate::authorize('delete', $terminal);

        // Terminal soft-deletes, so the FK's cascadeOnDelete on
        // shifts.terminal_id never fires -- an open shift would survive with
        // a soft-deleted parent, and since the shift picker only lists
        // active terminals, it could then never be reached to be closed,
        // leaving its cash permanently unreconciled.
        if (Shift::where('terminal_id', $terminal->id)->where('status', Shift::STATUS_OPEN)->exists()) {
            session()->flash('error', 'This terminal has an open shift. Close it before deleting the terminal.');

            return;
        }

        $terminal->delete();

        session()->flash('status', 'Terminal deleted.');
    }

    public function render()
    {
        Gate::authorize('viewAny', Terminal::class);

        $terminals = Terminal::query()
            ->with('stockLocation')
            ->when(trim($this->search) !== '', function ($q) {
                $term = trim($this->search);
                $q->where(fn ($query) => $query->where('name', 'like', "%{$term}%")
                    ->orWhere('code', 'like', "%{$term}%"));
            })
            ->orderBy('name')
            ->paginate(20);

        return view('livewire.sales.terminals.index', [
            'terminals' => $terminals,
            'totalTerminals' => Terminal::count(),
            'activeTerminals' => Terminal::where('is_active', true)->count(),
            'printerConfigured' => Terminal::whereNotNull('printer_connector')->whereNotNull('receipt_printer')->count(),
        ]);
    }
}
