<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Settings\LabelSettings;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Labels extends Component
{
    public int $width_mm = 50;

    public int $height_mm = 30;

    public int $gap_mm = 2;

    public int $density = 8;

    public function mount(): void
    {
        Gate::authorize('config.manage');

        $settings = app(LabelSettings::class);
        $this->width_mm = $settings->width_mm;
        $this->height_mm = $settings->height_mm;
        $this->gap_mm = $settings->gap_mm;
        $this->density = $settings->density;
    }

    public function rules(): array
    {
        return [
            'width_mm' => ['required', 'integer', 'min:1', 'max:200'],
            'height_mm' => ['required', 'integer', 'min:1', 'max:200'],
            'gap_mm' => ['required', 'integer', 'min:0', 'max:50'],
            'density' => ['required', 'integer', 'min:1', 'max:15'],
        ];
    }

    public function save(): void
    {
        Gate::authorize('config.manage');

        $validated = $this->validate();

        $settings = app(LabelSettings::class);
        $settings->width_mm = $validated['width_mm'];
        $settings->height_mm = $validated['height_mm'];
        $settings->gap_mm = $validated['gap_mm'];
        $settings->density = $validated['density'];
        $settings->save();

        session()->flash('status', 'Label settings saved.');
    }

    public function render()
    {
        return view('livewire.settings.labels');
    }
}
