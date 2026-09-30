<?php

use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    /**
     * Send an email verification notification to the user.
     */
    public function sendVerification(): void
    {
        if (Auth::user()->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);

            return;
        }

        Auth::user()->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }

    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-auth-header title="Verifikasi Email" description="Cek inbox kamu buat lanjut." />

    <p class="text-sm text-zinc-500">
        Konfirmasi alamat emailmu dengan klik link yang baru kami kirim ke emailmu.
    </p>

    @if (session('status') == 'verification-link-sent')
        <div class="rounded-xl bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
            Link verifikasi baru sudah dikirim ke email yang kamu daftarkan.
        </div>
    @endif

    <div class="flex flex-col gap-3">
        <flux:button wire:click="sendVerification" variant="primary" class="w-full">
            Kirim Ulang Email Verifikasi
        </flux:button>

        <button
            wire:click="logout"
            type="submit"
            class="text-center text-sm text-zinc-500 underline transition hover:text-zinc-900"
        >
            Keluar
        </button>
    </div>
</div>
