<?php

use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Services\QuizAttemptService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public Quiz $quiz;

    public QuizAttempt $attempt;

    /** @var array<string, string> question_id => selected_option_id */
    public array $answers = [];

    public function mount(Quiz $quiz, QuizAttemptService $quizAttemptService): void
    {
        $this->authorize('view', $quiz);

        abort_if($quiz->questions()->doesntExist(), 404);

        $this->quiz = $quiz;
        $this->attempt = $quizAttemptService->startOrResume($quiz, Auth::user());
    }

    #[Computed]
    public function questions()
    {
        return $this->quiz->questions()->with('options')->get();
    }

    public function submit(QuizAttemptService $quizAttemptService): void
    {
        if ($this->attempt->isCompleted()) {
            return;
        }

        if (RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            Flux::toast(text: 'Terlalu banyak percobaan submit, coba lagi sebentar lagi.', variant: 'danger');

            return;
        }

        RateLimiter::hit($this->throttleKey(), 60);

        foreach ($this->questions as $question) {
            if (empty($this->answers[$question->id])) {
                Flux::toast(text: 'Semua soal harus dijawab sebelum submit.', variant: 'danger');

                return;
            }
        }

        $this->attempt = $quizAttemptService->submit($this->attempt, $this->answers);
    }

    protected function throttleKey(): string
    {
        return 'quiz-attempt-submit:'.$this->attempt->id;
    }
}; ?>

<div class="flex flex-col gap-6">
    <div>
        <flux:link :href="route('quizzes.index')" wire:navigate class="text-sm">&larr; Kembali ke Daftar Quiz</flux:link>
    </div>

    <div>
        <flux:heading size="xl" class="font-display">{{ $quiz->title }}</flux:heading>
        @if ($quiz->description)
            <flux:subheading>{{ $quiz->description }}</flux:subheading>
        @endif
    </div>

    @if ($attempt->isCompleted())
        <div class="rounded-2xl border border-zinc-200 p-10 text-center dark:border-zinc-700">
            <flux:heading size="lg" class="font-display">Quiz Selesai</flux:heading>
            <p class="mt-2 font-display text-4xl font-extrabold text-zinc-900 dark:text-white">{{ $attempt->score }}%</p>
            @if ($quiz->passing_score !== null)
                <flux:badge :color="$attempt->hasPassed() ? 'green' : 'red'" class="mt-3">
                    {{ $attempt->hasPassed() ? 'Lulus' : 'Belum Lulus' }}
                </flux:badge>
            @endif
            <div class="mt-6 flex justify-center gap-2">
                <flux:button variant="primary" :href="route('quizzes.attempts.result', $attempt)" wire:navigate class="transition hover:-translate-y-0.5">Lihat Detail Hasil</flux:button>
                <flux:button :href="route('quizzes.index')" wire:navigate class="transition hover:-translate-y-0.5">Kembali ke Daftar Quiz</flux:button>
            </div>
        </div>
    @else
        <form wire:submit="submit" class="flex flex-col gap-5">
            @foreach ($this->questions as $index => $question)
                <div wire:key="question-{{ $question->id }}" class="rounded-2xl border border-zinc-200 p-5 dark:border-zinc-700">
                    <div class="flex gap-3">
                        <div class="flex size-7 shrink-0 items-center justify-center rounded-full bg-blue-100 font-display text-xs font-bold text-accent dark:bg-blue-950">
                            {{ $index + 1 }}
                        </div>
                        <p class="pt-0.5 font-semibold text-zinc-900 dark:text-white">{{ $question->question_text }}</p>
                    </div>

                    <div class="mt-4 flex flex-col gap-2.5 pl-10">
                        @foreach ($question->options as $option)
                            <label class="flex items-center gap-2.5 rounded-xl border border-zinc-200 px-4 py-3 text-sm text-zinc-700 transition has-[:checked]:border-accent has-[:checked]:bg-blue-50 has-[:checked]:font-semibold has-[:checked]:text-blue-900 dark:border-zinc-700 dark:text-zinc-300">
                                <input type="radio" wire:model="answers.{{ $question->id }}" value="{{ $option->id }}" class="size-4 accent-accent" />
                                {{ $option->option_text }}
                            </label>
                        @endforeach
                    </div>
                </div>
            @endforeach

            <flux:button variant="primary" type="submit" class="self-start transition hover:-translate-y-0.5">Submit Jawaban</flux:button>
        </form>
    @endif
</div>
