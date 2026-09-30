<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    public string $password = '';

    /**
     * Confirm the current user's password.
     */
    public function confirmPassword(): void
    {
        $this->validate([
            'password' => ['required', 'string'],
        ]);

        if (!Auth::guard('web')->validate([
            'email' => Auth::user()->email,
            'password' => $this->password,
        ])) {
            throw ValidationException::withMessages([
                'password' => 'Password yang kamu masukkan salah.',
            ]);
        }

        session(['auth.password_confirmed_at' => time()]);

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-auth-header
        title="Konfirmasi Password"
        description="Ini area yang perlu keamanan tambahan. Konfirmasi password kamu dulu sebelum lanjut."
    />

    <!-- Session Status -->
    <x-auth-session-status :status="session('status')" />

    <form wire:submit="confirmPassword" class="flex flex-col gap-5">
        <!-- Password -->
        <flux:input
            wire:model="password"
            id="password"
            label="Password"
            type="password"
            name="password"
            required
            autocomplete="new-password"
            placeholder="Password"
        />

        <flux:button variant="primary" type="submit" class="w-full">Konfirmasi</flux:button>
    </form>
</div>
