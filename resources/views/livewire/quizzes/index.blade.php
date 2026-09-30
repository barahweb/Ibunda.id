<?php

use App\Models\Quiz;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;

    #[Computed]
    public function quizzes()
    {
        return Quiz::query()
            ->where('status', Quiz::STATUS_PUBLISHED)
            ->has('questions')
            ->withCount('questions')
            ->latest()
            ->paginate(9);
    }
}; ?>

<div class="flex flex-col gap-6">
    <div>
        <flux:heading size="xl" class="font-display">Daftar Quiz</flux:heading>
        <flux:subheading>Pilih quiz yang mau kamu kerjakan.</flux:subheading>
    </div>

    <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($this->quizzes as $quiz)
            <div wire:key="quiz-{{ $quiz->id }}" class="flex flex-col justify-between rounded-2xl border border-zinc-200 p-5 transition duration-200 hover:-translate-y-1 hover:shadow-lg dark:border-zinc-700">
                <div>
                    <div class="flex size-10 items-center justify-center rounded-xl bg-blue-100 text-accent dark:bg-blue-950">
                        <flux:icon.pencil-square class="size-5" />
                    </div>
                    <flux:heading size="lg" class="mt-4 font-display">{{ $quiz->title }}</flux:heading>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ $quiz->description ? str($quiz->description)->limit(100) : 'Tidak ada deskripsi.' }}</p>
                    <div class="mt-3 flex gap-3 text-xs font-semibold text-zinc-400">
                        <span>{{ $quiz->questions_count }} soal</span>
                        @if ($quiz->time_limit_minutes)
                            <span>{{ $quiz->time_limit_minutes }} menit</span>
                        @endif
                    </div>
                </div>

                <flux:button variant="primary" class="mt-5 transition hover:-translate-y-0.5" :href="route('quizzes.attempt', $quiz)" wire:navigate>
                    Mulai Quiz
                </flux:button>
            </div>
        @empty
            <div class="col-span-full rounded-2xl border border-dashed border-zinc-300 p-12 text-center dark:border-zinc-700">
                <flux:icon.pencil-square class="mx-auto size-8 text-zinc-300" />
                <p class="mt-3 text-sm text-zinc-500 dark:text-zinc-400">Belum ada quiz yang tersedia.</p>
            </div>
        @endforelse
    </div>

    {{ $this->quizzes->links() }}
</div>
