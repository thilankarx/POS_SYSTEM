<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Settings\TaxSettings;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class TaxDefaults extends Component
{
    public bool $prices_include_tax = false;

    public function mount(): void
    {
        Gate::authorize('config.manage');

        $this->prices_include_tax = app(TaxSettings::class)->prices_include_tax;
    }

    public function save(): void
    {
        Gate::authorize('config.manage');

        $settings = app(TaxSettings::class);
        $settings->prices_include_tax = $this->prices_include_tax;
        $settings->save();

        session()->flash('status', 'Tax defaults saved.');
    }

    public function render()
    {
        return view('livewire.settings.tax-defaults');
    }
}
