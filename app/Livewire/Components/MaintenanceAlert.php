<?php

namespace App\Livewire\Components;

use App\Models\Setting;
use Livewire\Component;

class MaintenanceAlert extends Component
{
    public bool $maintenanceActive = false;
    public string $maintenanceMessage = '';
    public bool $isAdmin = false;

    #[\Livewire\Attributes\On('maintenance-check')]
    public function checkMaintenanceStatus(): void
    {
        $this->maintenanceActive = Setting::getBool('maintenance_mode', false);
        $this->maintenanceMessage = Setting::get('maintenance_message', 'The system is currently under maintenance. Please try again later.');
        $this->isAdmin = auth()->check() && auth()->user()->isAdmin();
    }

    public function mount(): void
    {
        $this->isAdmin = auth()->check() && auth()->user()->isAdmin();
        $this->checkMaintenanceStatus();
    }

    public function render()
    {
        return view('livewire.components.maintenance-alert');
    }

    public function goHome(): void
    {
        $this->redirect(route('home'));
    }

    public function logout(): void
    {
        auth()->logout();
        session()->invalidate();
        session()->regenerateToken();
        $this->redirect(route('login'));
    }
}
