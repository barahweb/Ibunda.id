<?php

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    /**
     * Handle an incoming registration request.
     */
    public function register(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:' . User::class],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        $validated['password'] = Hash::make($validated['password']);

        event(new Registered(($user = User::create($validated))));

        Auth::login($user);

        $this->redirect(route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-auth-header title="Buat Akun Baru" description="Isi data di bawah buat mulai pakai Quiz Assessment." />

    <!-- Session Status -->
    <x-auth-session-status :status="session('status')" />

    <form wire:submit="register" class="flex flex-col gap-5">
        <!-- Name -->
        <flux:input wire:model="name" id="name" label="Nama" type="text" name="name" required autofocus autocomplete="name" placeholder="Nama lengkap" />

        <!-- Email Address -->
        <flux:input wire:model="email" id="email" label="Email" type="email" name="email" required autocomplete="email" placeholder="email@example.com" />

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

        <!-- Confirm Password -->
        <flux:input
            wire:model="password_confirmation"
            id="password_confirmation"
            label="Konfirmasi Password"
            type="password"
            name="password_confirmation"
            required
            autocomplete="new-password"
            placeholder="Ulangi password"
        />

        <flux:button type="submit" variant="primary" class="w-full">
            Buat Akun
        </flux:button>
    </form>

    <p class="text-center text-sm text-zinc-500">
        Sudah punya akun?
        <x-text-link href="{{ route('login') }}">Masuk</x-text-link>
    </p>
</div>
