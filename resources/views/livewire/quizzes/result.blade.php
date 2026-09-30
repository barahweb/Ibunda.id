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

    <div class="rounded-2xl border border-zinc-200 p-10 text-center dark:border-zinc-700">
        <flux:heading size="xl" class="font-display">{{ $attempt->quiz->title }}</flux:heading>
        <p class="mt-2 font-display text-5xl font-extrabold {{ $attempt->quiz->passing_score !== null && $attempt->hasPassed() ? 'text-red-500' : 'text-accent' }}">{{ $attempt->score }}%</p>
        @if ($attempt->quiz->passing_score !== null)
            <flux:badge :color="$attempt->hasPassed() ? 'green' : 'red'" class="mt-3">
                {{ $attempt->hasPassed() ? 'Lulus' : 'Belum Lulus' }} &middot; Nilai lulus {{ $attempt->quiz->passing_score }}%
            </flux:badge>
        @endif
        <p class="mt-3 text-xs text-zinc-400">
            Dikerjakan {{ $attempt->started_at->format('d M Y H:i') }}, submit {{ $attempt->submitted_at->format('d M Y H:i') }}
        </p>
    </div>

    <div class="flex flex-col gap-3">
        @foreach ($this->answers as $index => $answer)
            <div class="rounded-2xl border p-5 {{ $answer->is_correct ? 'border-emerald-200 dark:border-emerald-900' : 'border-red-200 dark:border-red-900' }}">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex gap-3">
                        <div class="flex size-7 shrink-0 items-center justify-center rounded-full font-display text-xs font-bold {{ $answer->is_correct ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-950' : 'bg-red-100 text-red-600 dark:bg-red-950' }}">
                            {{ $index + 1 }}
                        </div>
                        <p class="pt-0.5 font-semibold text-zinc-900 dark:text-white">{{ $answer->question->question_text }}</p>
                    </div>
                    <flux:badge :color="$answer->is_correct ? 'green' : 'red'" size="sm" class="shrink-0">
                        {{ $answer->is_correct ? 'Benar' : 'Salah' }}
                    </flux:badge>
                </div>

                <div class="mt-3 space-y-1 pl-10 text-sm">
                    <p class="text-zinc-500 dark:text-zinc-400">
                        Jawabanmu: <span class="font-semibold text-zinc-900 dark:text-white">{{ $answer->selectedOption->option_text ?? '(tidak dijawab)' }}</span>
                    </p>
                    @unless ($answer->is_correct)
                        <p class="text-zinc-500 dark:text-zinc-400">
                            Jawaban benar: <span class="font-semibold text-emerald-600 dark:text-emerald-400">{{ $answer->question->correctOption->option_text ?? 'Soal ini sudah diubah, jawaban benar tidak tersedia lagi.' }}</span>
                        </p>
                    @endunless
                </div>
            </div>
        @endforeach
    </div>
</div>
