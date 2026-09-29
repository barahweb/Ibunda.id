<?php

use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Services\QuizAttemptService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
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

        foreach ($this->questions as $question) {
            if (empty($this->answers[$question->id])) {
                Flux::toast(text: 'Semua soal harus dijawab sebelum submit.', variant: 'danger');

                return;
            }
        }

        $this->attempt = $quizAttemptService->submit($this->attempt, $this->answers);
    }
}; ?>

<div class="flex flex-col gap-6">
    <div>
        <flux:link :href="route('quizzes.index')" wire:navigate class="text-sm">&larr; Kembali ke Daftar Quiz</flux:link>
    </div>

    <div>
        <flux:heading size="xl">{{ $quiz->title }}</flux:heading>
        @if ($quiz->description)
            <flux:subheading>{{ $quiz->description }}</flux:subheading>
        @endif
    </div>

    @if ($attempt->isCompleted())
        <div class="rounded-lg border border-zinc-200 p-6 text-center dark:border-zinc-700">
            <flux:heading size="lg">Quiz Selesai</flux:heading>
            <p class="mt-2 text-3xl font-bold text-zinc-900 dark:text-white">{{ $attempt->score }}%</p>
            @if ($quiz->passing_score !== null)
                <flux:badge :color="$attempt->hasPassed() ? 'green' : 'red'" class="mt-2">
                    {{ $attempt->hasPassed() ? 'Lulus' : 'Belum Lulus' }}
                </flux:badge>
            @endif
            <div class="mt-4">
                <flux:button :href="route('quizzes.index')" wire:navigate>Kembali ke Daftar Quiz</flux:button>
            </div>
        </div>
    @else
        <form wire:submit="submit" class="flex flex-col gap-6">
            @foreach ($this->questions as $index => $question)
                <div wire:key="question-{{ $question->id }}" class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                    <p class="font-medium text-zinc-900 dark:text-white">{{ $index + 1 }}. {{ $question->question_text }}</p>

                    <div class="mt-3 flex flex-col gap-2">
                        @foreach ($question->options as $option)
                            <label class="flex items-center gap-2 text-sm text-zinc-700 dark:text-zinc-300">
                                <input type="radio" wire:model="answers.{{ $question->id }}" value="{{ $option->id }}" class="size-4" />
                                {{ $option->option_text }}
                            </label>
                        @endforeach
                    </div>
                </div>
            @endforeach

            <flux:button variant="primary" type="submit" class="self-start">Submit Jawaban</flux:button>
        </form>
    @endif
</div>
