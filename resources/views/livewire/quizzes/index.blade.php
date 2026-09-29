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
        <flux:heading size="xl">Daftar Quiz</flux:heading>
        <flux:subheading>Pilih quiz yang mau kamu kerjakan.</flux:subheading>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($this->quizzes as $quiz)
            <div wire:key="quiz-{{ $quiz->id }}" class="flex flex-col justify-between rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                <div>
                    <flux:heading size="lg">{{ $quiz->title }}</flux:heading>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ $quiz->description ? str($quiz->description)->limit(100) : 'Tidak ada deskripsi.' }}</p>
                    <div class="mt-3 flex gap-3 text-xs text-zinc-500 dark:text-zinc-400">
                        <span>{{ $quiz->questions_count }} soal</span>
                        @if ($quiz->time_limit_minutes)
                            <span>{{ $quiz->time_limit_minutes }} menit</span>
                        @endif
                    </div>
                </div>

                <flux:button variant="primary" class="mt-4" :href="route('quizzes.attempt', $quiz)" wire:navigate>
                    Mulai Quiz
                </flux:button>
            </div>
        @empty
            <div class="col-span-full rounded-lg border border-dashed border-zinc-300 p-8 text-center text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                Belum ada quiz yang tersedia.
            </div>
        @endforelse
    </div>

    {{ $this->quizzes->links() }}
</div>
