<?php

use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    //
}; ?>

<div class="flex flex-col items-start">
    @include('partials.settings-heading')

    <x-settings.layout heading="Tampilan" subheading="Atur tampilan akunmu.">
        <div class="flex items-center gap-3 rounded-xl border border-zinc-200 p-4">
            <div class="flex size-9 items-center justify-center rounded-lg bg-blue-100 text-accent">
                <flux:icon.sun class="size-5" />
            </div>
            <div>
                <p class="text-sm font-semibold text-zinc-900">Mode Terang</p>
                <p class="text-sm text-zinc-500">Saat ini aplikasi cuma tersedia dalam tampilan terang.</p>
            </div>
        </div>
    </x-settings.layout>
</div>
