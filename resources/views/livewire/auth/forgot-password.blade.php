<?php

use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    public string $email = '';

    /**
     * Send a password reset link to the provided email address.
     */
    public function sendPasswordResetLink(): void
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        Password::sendResetLink($this->only('email'));

        session()->flash('status', 'Link reset akan dikirim kalau akunnya ada.');
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-auth-header title="Lupa Password" description="Masukkan email kamu buat dapat link reset password." />

    <!-- Session Status -->
    <x-auth-session-status :status="session('status')" />

    <form wire:submit="sendPasswordResetLink" class="flex flex-col gap-5">
        <!-- Email Address -->
        <flux:input wire:model="email" label="Email" type="email" name="email" required autofocus placeholder="email@example.com" />

        <flux:button variant="primary" type="submit" class="w-full">Kirim Link Reset</flux:button>
    </form>

    <p class="text-center text-sm text-zinc-500">
        Sudah ingat lagi?
        <x-text-link href="{{ route('login') }}">Masuk</x-text-link>
    </p>
</div>
