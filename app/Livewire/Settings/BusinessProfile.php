<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Settings\BusinessProfileSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class BusinessProfile extends Component
{
    use WithFileUploads;

    public string $store_name = '';

    public string $address = '';

    public string $phone = '';

    public string $receipt_header = '';

    public string $receipt_footer = '';

    public ?string $logo_path = null;

    public ?UploadedFile $logo = null;

    public string $business_type = BusinessProfileSettings::BUSINESS_TYPE_RETAIL;

    public function mount(): void
    {
        Gate::authorize('config.manage');

        $settings = app(BusinessProfileSettings::class);
        $this->store_name = $settings->store_name;
        $this->address = $settings->address;
        $this->phone = $settings->phone;
        $this->receipt_header = $settings->receipt_header;
        $this->receipt_footer = $settings->receipt_footer;
        $this->logo_path = $settings->logo_path;
        $this->business_type = $settings->business_type;
    }

    public function removeLogo(): void
    {
        Gate::authorize('config.manage');

        $settings = app(BusinessProfileSettings::class);

        if ($settings->logo_path) {
            Storage::disk('public')->delete($settings->logo_path);
        }

        $settings->logo_path = null;
        $settings->save();

        $this->logo_path = null;
        session()->flash('status', 'Logo removed.');
    }

    public function save(): void
    {
        Gate::authorize('config.manage');

        $validated = $this->validate([
            'store_name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:64'],
            'receipt_header' => ['nullable', 'string', 'max:255'],
            'receipt_footer' => ['nullable', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:png', 'dimensions:max_width=300,max_height=180', 'max:2048'],
            'business_type' => ['required', Rule::in([
                BusinessProfileSettings::BUSINESS_TYPE_RETAIL,
                BusinessProfileSettings::BUSINESS_TYPE_HARDWARE,
                BusinessProfileSettings::BUSINESS_TYPE_RESTAURANT,
            ])],
        ]);

        $settings = app(BusinessProfileSettings::class);
        $settings->store_name = $validated['store_name'];
        $settings->address = $validated['address'] ?? '';
        $settings->phone = $validated['phone'] ?? '';
        $settings->receipt_header = $validated['receipt_header'] ?? '';
        $settings->receipt_footer = $validated['receipt_footer'] ?? '';
        $settings->business_type = $validated['business_type'];

        if ($this->logo !== null) {
            if ($settings->logo_path) {
                Storage::disk('public')->delete($settings->logo_path);
            }

            $settings->logo_path = $this->logo->store('branding', 'public');
            $this->logo_path = $settings->logo_path;
            $this->logo = null;
        }

        $settings->save();

        session()->flash('status', 'Business profile saved.');
    }

    public function render()
    {
        return view('livewire.settings.business-profile');
    }
}
