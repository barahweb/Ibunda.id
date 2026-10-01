<?php

use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Services\AssessmentAttemptService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Renderless;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public Assessment $assessment;

    public AssessmentAttempt $attempt;

    /** @var array<string, string> question_id => nilai Likert 1-5 */
    public array $answers = [];

    public function mount(Assessment $assessment, AssessmentAttemptService $assessmentAttemptService): void
    {
        $this->authorize('view', $assessment);

        abort_if($assessment->questions()->doesntExist(), 404);

        $this->assessment = $assessment;
        $this->attempt = $assessmentAttemptService->startOrResume($assessment, Auth::user());

        // Cuma jawaban buat soal yang masih ada, biar soal yang udah dihapus admin
        // gak ikut kehitung di progress.
        $this->answers = $this->attempt->answers()
            ->whereNotNull('value')
            ->whereHas('question')
            ->pluck('value', 'assessment_question_id')
            ->map(fn ($value) => (string) $value)
            ->all();
    }

    #[Computed]
    public function questions()
    {
        return $this->assessment->questions()->get();
    }

    #[Renderless]
    public function saveAnswer(string $questionId, int $value, AssessmentAttemptService $assessmentAttemptService): void
    {
        $assessmentAttemptService->saveAnswer($this->attempt, $questionId, $value);
    }

    public function submit(AssessmentAttemptService $assessmentAttemptService): void
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
            if (!in_array((string) ($this->answers[$question->id] ?? ''), ['1', '2', '3', '4', '5'], true)) {
                Flux::toast(text: 'Semua pernyataan harus dijawab (skala 1 sampai 5) sebelum submit.', variant: 'danger');

                return;
            }
        }

        $this->attempt = $assessmentAttemptService->submit($this->attempt, $this->answers);
    }

    protected function throttleKey(): string
    {
        return 'assessment-attempt-submit:'.$this->attempt->id;
    }
}; ?>

<div class="flex flex-col gap-6">
    <div>
        <flux:link :href="route('assessments.index')" wire:navigate class="text-sm">&larr; Kembali ke Daftar Tes</flux:link>
    </div>

    <div>
        <flux:heading size="xl" class="font-display">{{ $assessment->title }}</flux:heading>
        @if ($assessment->description)
            <flux:subheading>{{ $assessment->description }}</flux:subheading>
        @endif
    </div>

    @if ($attempt->isCompleted())
        <div class="rounded-2xl border border-zinc-200 p-10 text-center dark:border-zinc-700">
            <flux:heading size="lg" class="font-display">Tes Selesai</flux:heading>
            <p class="mt-2 font-display text-5xl font-extrabold tracking-widest text-accent">{{ $attempt->result_type }}</p>
            <div class="mt-6 flex justify-center gap-2">
                <flux:button variant="primary" :href="route('assessments.attempts.result', $attempt)" wire:navigate class="transition hover:-translate-y-0.5">Lihat Detail Hasil</flux:button>
                <flux:button :href="route('assessments.index')" wire:navigate class="transition hover:-translate-y-0.5">Kembali ke Daftar Tes</flux:button>
            </div>
        </div>
    @else
        <div
            x-data="{ get answered() { return Object.values($wire.answers).filter(Boolean).length; } }"
            class="sticky top-2 z-10 flex items-center justify-between rounded-2xl border border-zinc-200 bg-white/90 px-5 py-3 shadow-sm backdrop-blur"
        >
            <span class="text-sm font-semibold text-zinc-600">Terjawab</span>
            <span class="font-display text-xl font-extrabold tabular-nums text-zinc-900" x-text="`${answered}/{{ $this->questions->count() }}`"></span>
        </div>

        <p class="text-sm text-zinc-500">Geser pilihanmu ke arah pernyataan yang paling menggambarkan dirimu. Tengah (3) berarti netral.</p>

        <form wire:submit="submit" class="flex flex-col gap-5">
            @foreach ($this->questions as $index => $question)
                <div wire:key="question-{{ $question->id }}" class="rounded-2xl border border-zinc-200 p-5 dark:border-zinc-700">
                    <div class="flex gap-3">
                        <div class="flex size-7 shrink-0 items-center justify-center rounded-full bg-blue-100 font-display text-xs font-bold text-accent dark:bg-blue-950">
                            {{ $index + 1 }}
                        </div>

                        <div class="flex-1">
                            <div class="flex items-center justify-between gap-4 text-sm font-semibold text-zinc-900 dark:text-white">
                                <span class="max-w-[40%]">{{ $question->statement_left }}</span>
                                <span class="max-w-[40%] text-right">{{ $question->statement_right }}</span>
                            </div>

                            <div class="mt-3 flex justify-between gap-2">
                                @foreach ([1, 2, 3, 4, 5] as $value)
                                    <label class="flex size-10 cursor-pointer items-center justify-center rounded-full border border-zinc-200 text-sm font-semibold text-zinc-500 transition hover:border-accent has-[:checked]:border-accent has-[:checked]:bg-accent has-[:checked]:text-white dark:border-zinc-700">
                                        <input
                                            type="radio"
                                            class="sr-only"
                                            wire:model="answers.{{ $question->id }}"
                                            wire:change="saveAnswer('{{ $question->id }}', {{ $value }})"
                                            value="{{ $value }}"
                                        />
                                        {{ $value }}
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach

            <flux:button variant="primary" type="submit" class="self-start transition hover:-translate-y-0.5">Lihat Hasil</flux:button>
        </form>

        <x-oejts-attribution />
    @endif
</div>
