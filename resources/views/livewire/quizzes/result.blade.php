<?php

use App\Models\QuizAttempt;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public QuizAttempt $attempt;

    public function mount(QuizAttempt $attempt): void
    {
        $this->authorize('view', $attempt);

        abort_unless($attempt->isCompleted(), 404);

        $this->attempt = $attempt;
    }

    #[Computed]
    public function answers()
    {
        return $this->attempt->answers()
            ->with(['question' => fn ($query) => $query->withTrashed(), 'selectedOption' => fn ($query) => $query->withTrashed()])
            ->get()
            ->sortBy(fn ($answer) => $answer->question->order);
    }
}; ?>

<div class="flex flex-col gap-6">
    <div>
        <flux:link :href="route('quizzes.history')" wire:navigate class="text-sm">&larr; Kembali ke Riwayat</flux:link>
    </div>

    <div class="rounded-lg border border-zinc-200 p-6 text-center dark:border-zinc-700">
        <flux:heading size="xl">{{ $attempt->quiz->title }}</flux:heading>
        <p class="mt-2 text-4xl font-bold text-zinc-900 dark:text-white">{{ $attempt->score }}%</p>
        @if ($attempt->quiz->passing_score !== null)
            <flux:badge :color="$attempt->hasPassed() ? 'green' : 'red'" class="mt-2">
                {{ $attempt->hasPassed() ? 'Lulus' : 'Belum Lulus' }} &middot; Nilai lulus {{ $attempt->quiz->passing_score }}%
            </flux:badge>
        @endif
        <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
            Dikerjakan {{ $attempt->started_at->format('d M Y H:i') }}, submit {{ $attempt->submitted_at->format('d M Y H:i') }}
        </p>
    </div>

    <div class="flex flex-col gap-3">
        @foreach ($this->answers as $index => $answer)
            <div class="rounded-lg border p-4 {{ $answer->is_correct ? 'border-green-200 dark:border-green-900' : 'border-red-200 dark:border-red-900' }}">
                <div class="flex items-start justify-between gap-4">
                    <p class="font-medium text-zinc-900 dark:text-white">{{ $index + 1 }}. {{ $answer->question->question_text }}</p>
                    <flux:badge :color="$answer->is_correct ? 'green' : 'red'" size="sm">
                        {{ $answer->is_correct ? 'Benar' : 'Salah' }}
                    </flux:badge>
                </div>

                <div class="mt-3 space-y-1 text-sm">
                    <p class="text-zinc-600 dark:text-zinc-400">
                        Jawabanmu: <span class="font-medium text-zinc-900 dark:text-white">{{ $answer->selectedOption->option_text ?? '(tidak dijawab)' }}</span>
                    </p>
                    @unless ($answer->is_correct)
                        <p class="text-zinc-600 dark:text-zinc-400">
                            Jawaban benar: <span class="font-medium text-green-700 dark:text-green-400">{{ $answer->question->correctOption->option_text ?? 'Soal ini sudah diubah, jawaban benar tidak tersedia lagi.' }}</span>
                        </p>
                    @endunless
                </div>
            </div>
        @endforeach
    </div>
</div>
