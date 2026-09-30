<?php

use App\Models\User;
use App\Rules\Turnstile;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public string $turnstileToken = '';

    /**
     * Handle an incoming registration request.
     */
    public function register(): void
    {
        $this->ensureIsNotRateLimited();

        RateLimiter::hit($this->throttleKey(), 60);

        try {
            $validated = $this->validate([
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
                'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
                'turnstileToken' => ['required', 'string', new Turnstile],
            ], [
                'turnstileToken.required' => 'Centang dulu captcha "Verify you are human" di atas sebelum bikin akun.',
            ]);
        } catch (ValidationException $e) {
            // Token captcha yang udah dipakai gak bisa dipakai lagi, jadi widget-nya
            // perlu di-refresh di browser biar user bisa langsung coba ulang.
            $this->dispatch('turnstile-reset');

            throw $e;
        }

        unset($validated['turnstileToken']);
        $validated['password'] = Hash::make($validated['password']);

        event(new Registered(($user = User::create($validated))));

        Auth::login($user);

        $this->redirect(route('dashboard', absolute: false), navigate: true);
    }

    /**
     * Cegah satu IP daftar berkali-kali dalam waktu singkat (bot/spam akun).
     */
    protected function ensureIsNotRateLimited(): void
    {
        if (!RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => "Terlalu banyak percobaan daftar dari perangkat ini. Coba lagi dalam {$seconds} detik.",
        ]);
    }

    protected function throttleKey(): string
    {
        return Str::transliterate('register|'.request()->ip());
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

        <!-- Captcha -->
        <div>
            <div
                wire:ignore
                x-data="{
                    widgetId: null,
                    renderWidget() {
                        this.widgetId = window.turnstile.render(this.$el, {
                            sitekey: @js(config('services.turnstile.site_key')),
                            callback: (token) => { $wire.set('turnstileToken', token); },
                            'expired-callback': () => { $wire.set('turnstileToken', ''); },
                        });
                    },
                }"
                x-init="window.turnstile ? renderWidget() : window.addEventListener('turnstile-loaded', () => renderWidget())"
                x-on:turnstile-reset.window="widgetId !== null && window.turnstile.reset(widgetId)"
            >
                <script src="https://challenges.cloudflare.com/turnstile/v0/api.js?onload=onloadTurnstileCallback&render=explicit" defer></script>
                <script>
                    window.onloadTurnstileCallback = function () {
                        window.dispatchEvent(new Event('turnstile-loaded'));
                    };
                </script>
            </div>
            @error('turnstileToken')
                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <flux:button type="submit" variant="primary" class="w-full">
            Buat Akun
        </flux:button>
    </form>

    <p class="text-center text-sm text-zinc-500">
        Sudah punya akun?
        <x-text-link href="{{ route('login') }}">Masuk</x-text-link>
    </p>
</div>
