<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;

use function Livewire\Volt\{layout, title};

layout('layouts.app');
title('Two-Factor Authentication');

new class extends Component
{
    public string $code = '';

    public function enableTwoFactor()
    {
        $user = Auth::user();
        
        if (!$user->two_factor_secret) {
            app(\Laravel\Fortify\Actions\EnableTwoFactorAuthentication::class)($user);
        }
    }

    public function confirmTwoFactor()
    {
        $this->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        $user = Auth::user();
        
        if (!app(\Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider::class)->verify(
            decrypt($user->two_factor_secret),
            $this->code
        )) {
            $this->addError('code', 'Invalid verification code.');
            return;
        }

        app(\Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication::class)($user);

        session()->flash('message', 'Two-factor authentication enabled successfully.');
        $this->code = '';
    }

    public function disableTwoFactor()
    {
        $user = Auth::user();
        app(\Laravel\Fortify\Actions\DisableTwoFactorAuthentication::class)($user);
        session()->flash('message', 'Two-factor authentication disabled.');
        $this->code = '';
    }

    public function cancelSetup()
    {
        $user = Auth::user();
        if ($user->two_factor_secret && !$user->two_factor_confirmed_at) {
            $user->forceFill([
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
            ])->save();
        }
        $this->code = '';
    }

    public function with(): array
    {
        $user = Auth::user();
        
        return [
            'enabled' => $user->two_factor_confirmed_at !== null,
            'showingQrCode' => $user->two_factor_secret !== null && $user->two_factor_confirmed_at === null,
            'recoveryCodes' => json_decode(decrypt($user->two_factor_recovery_codes ?? '[]'), true) ?? [],
        ];
    }
}; ?>

<div class="max-w-2xl mx-auto">
    <h1 class="text-3xl font-bold mb-6">Two-Factor Authentication</h1>

    @if(session('message'))
        <x-ui.alert type="success" class="mb-6">
            {{ session('message') }}
        </x-ui.alert>
    @endif

    <x-ui.card>
        @if($enabled)
            <div class="mb-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">Two-Factor Authentication is Enabled</h2>
                        <p class="text-sm text-gray-600 mt-1">
                            Your account is protected with two-factor authentication.
                        </p>
                    </div>
                    <span class="inline-flex items-center rounded-full bg-green-100 px-3 py-1 text-sm font-medium text-green-800">
                        Enabled
                    </span>
                </div>
            </div>

            @if(count($recoveryCodes) > 0)
                <div class="mb-4 p-4 bg-yellow-50 border border-yellow-200 rounded-md">
                    <h3 class="text-sm font-medium text-yellow-800 mb-2">Recovery Codes</h3>
                    <p class="text-sm text-yellow-700 mb-3">
                        Save these recovery codes in a safe place. You can use them to access your account if you lose your device.
                    </p>
                    <div class="grid grid-cols-2 gap-2 font-mono text-sm">
                        @foreach($recoveryCodes as $code)
                            <div class="bg-white px-3 py-2 rounded border">{{ $code }}</div>
                        @endforeach
                    </div>
                </div>
            @endif

            <x-ui.button wire:click="disableTwoFactor" wire:confirm="Are you sure you want to disable two-factor authentication?" variant="danger">
                Disable Two-Factor Authentication
            </x-ui.button>
        @elseif($showingQrCode)
            <div class="mb-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Set Up Two-Factor Authentication</h2>
                <p class="text-sm text-gray-600 mb-4">
                    Scan this QR code with your authenticator app (Google Authenticator, Authy, etc.)
                </p>
                
                <div class="mb-4 flex justify-center">
                    {!! Auth::user()->twoFactorQrCodeSvg() !!}
                </div>

                <div class="mb-4">
                    <p class="text-sm font-medium text-gray-700 mb-2">Or enter this code manually:</p>
                    <div class="bg-gray-50 px-4 py-2 rounded-md font-mono text-sm break-all">
                        {{ decrypt(Auth::user()->two_factor_secret) }}
                    </div>
                </div>

                <div class="mb-4">
                    <x-ui.input
                        label="Enter verification code to confirm"
                        name="code"
                        wire:model="code"
                        maxlength="6"
                        pattern="[0-9]{6}"
                        placeholder="000000"
                        autocomplete="one-time-code"
                    />
                </div>

                <form wire:submit="confirmTwoFactor" class="space-y-4">
                    <div class="flex gap-3">
                        <x-ui.button type="submit" variant="primary">
                            Confirm and Enable
                        </x-ui.button>
                        <x-ui.button type="button" wire:click="cancelSetup" variant="outline">
                            Cancel
                        </x-ui.button>
                    </div>
                </form>
            </div>
        @else
            <div>
                <h2 class="text-lg font-semibold text-gray-900 mb-2">Enable Two-Factor Authentication</h2>
                <p class="text-sm text-gray-600 mb-4">
                    Add an extra layer of security to your account by enabling two-factor authentication.
                </p>
                <x-ui.button wire:click="enableTwoFactor" variant="primary">
                    Enable Two-Factor Authentication
                </x-ui.button>
            </div>
        @endif
    </x-ui.card>
</div>
