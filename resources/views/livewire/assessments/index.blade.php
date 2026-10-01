<?php

use App\Models\Assessment;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;

    #[Computed]
    public function assessments()
    {
        return Assessment::query()
            ->where('status', Assessment::STATUS_PUBLISHED)
            ->has('questions')
            ->withCount('questions')
            ->latest()
            ->paginate(9);
    }
}; ?>

<div class="flex flex-col gap-6">
    <div>
        <flux:heading size="xl" class="font-display">Tes Kepribadian</flux:heading>
        <flux:subheading>Pilih tes yang mau kamu kerjakan. Gak ada jawaban benar atau salah.</flux:subheading>
    </div>

    <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($this->assessments as $assessment)
            <div wire:key="assessment-{{ $assessment->id }}" class="flex flex-col justify-between rounded-2xl border border-zinc-200 p-5 transition duration-200 hover:-translate-y-1 hover:shadow-lg dark:border-zinc-700">
                <div>
                    <div class="flex size-10 items-center justify-center rounded-xl bg-blue-100 text-accent dark:bg-blue-950">
                        <flux:icon.face-smile class="size-5" />
                    </div>
                    <flux:heading size="lg" class="mt-4 font-display">{{ $assessment->title }}</flux:heading>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ $assessment->description ? str($assessment->description)->limit(100) : 'Tidak ada deskripsi.' }}</p>
                    <div class="mt-3 flex gap-3 text-xs font-semibold text-zinc-400">
                        <span>{{ $assessment->questions_count }} pernyataan</span>
                    </div>
                </div>

                <flux:button variant="primary" class="mt-5 w-full transition hover:-translate-y-0.5" :href="route('assessments.attempt', $assessment)" wire:navigate>
                    Mulai Tes
                </flux:button>
            </div>
        @empty
            <div class="rounded-2xl border border-dashed border-zinc-300 p-10 text-center sm:col-span-2 lg:col-span-3 dark:border-zinc-700">
                <flux:icon.face-smile class="mx-auto size-8 text-zinc-300" />
                <p class="mt-3 text-sm text-zinc-500 dark:text-zinc-400">Belum ada tes kepribadian yang tersedia.</p>
            </div>
        @endforelse
    </div>

    {{ $this->assessments->links() }}

    <x-oejts-attribution />
</div>
