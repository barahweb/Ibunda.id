<?php

use App\Models\Quiz;
use App\Models\QuizAttempt;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    #[Computed]
    public function isAdmin(): bool
    {
        return Auth::user()->isAdmin();
    }

    #[Computed]
    public function adminStats(): array
    {
        return [
            'total_quizzes' => Quiz::query()->count(),
            'published_quizzes' => Quiz::query()->where('status', Quiz::STATUS_PUBLISHED)->count(),
            'total_submissions' => QuizAttempt::query()->where('status', QuizAttempt::STATUS_COMPLETED)->count(),
        ];
    }

    #[Computed]
    public function participantStats(): array
    {
        $completedAttempts = QuizAttempt::query()
            ->where('user_id', Auth::id())
            ->where('status', QuizAttempt::STATUS_COMPLETED);

        return [
            'available_quizzes' => Quiz::query()->where('status', Quiz::STATUS_PUBLISHED)->count(),
            'completed_attempts' => (clone $completedAttempts)->count(),
            'average_score' => (clone $completedAttempts)->avg('score'),
        ];
    }

    #[Computed]
    public function inProgressAttempt(): ?QuizAttempt
    {
        return QuizAttempt::query()
            ->where('user_id', Auth::id())
            ->where('status', QuizAttempt::STATUS_IN_PROGRESS)
            ->with('quiz')
            ->latest('started_at')
            ->first();
    }

    #[Computed]
    public function recentAttempts()
    {
        return QuizAttempt::query()
            ->where('user_id', Auth::id())
            ->where('status', QuizAttempt::STATUS_COMPLETED)
            ->with('quiz')
            ->latest('submitted_at')
            ->limit(3)
            ->get();
    }
}; ?>

<div class="flex flex-1 flex-col gap-7">
    <div class="flex items-center gap-3">
        <flux:heading size="xl">Halo, {{ Auth::user()->name }}!</flux:heading>
        <flux:badge :color="$this->isAdmin ? 'blue' : 'zinc'" size="sm">{{ $this->isAdmin ? 'Admin' : 'Peserta' }}</flux:badge>
    </div>

    @if ($this->isAdmin)
        <p class="text-sm text-zinc-500">Ringkasan quiz yang kamu kelola.</p>

        <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
            <div class="rounded-2xl border border-zinc-200 p-5 transition duration-200 hover:-translate-y-1 hover:shadow-lg dark:border-zinc-700">
                <div class="flex size-10 items-center justify-center rounded-xl bg-blue-100 text-accent dark:bg-blue-950">
                    <flux:icon.clipboard-document-list class="size-5" />
                </div>
                <p class="mt-4 font-display text-2xl font-extrabold text-zinc-900 dark:text-white">{{ $this->adminStats['total_quizzes'] }}</p>
                <p class="mt-0.5 text-sm font-semibold text-zinc-500 dark:text-zinc-400">Total Quiz</p>
            </div>
            <div class="rounded-2xl border border-zinc-200 p-5 transition duration-200 hover:-translate-y-1 hover:shadow-lg dark:border-zinc-700">
                <div class="flex size-10 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600 dark:bg-emerald-950">
                    <flux:icon.check-circle class="size-5" />
                </div>
                <p class="mt-4 font-display text-2xl font-extrabold text-zinc-900 dark:text-white">{{ $this->adminStats['published_quizzes'] }}</p>
                <p class="mt-0.5 text-sm font-semibold text-zinc-500 dark:text-zinc-400">Published</p>
            </div>
            <div class="rounded-2xl border border-zinc-200 p-5 transition duration-200 hover:-translate-y-1 hover:shadow-lg dark:border-zinc-700">
                <div class="flex size-10 items-center justify-center rounded-xl bg-amber-100 text-amber-600 dark:bg-amber-950">
                    <flux:icon.chart-bar class="size-5" />
                </div>
                <p class="mt-4 font-display text-2xl font-extrabold text-zinc-900 dark:text-white">{{ $this->adminStats['total_submissions'] }}</p>
                <p class="mt-0.5 text-sm font-semibold text-zinc-500 dark:text-zinc-400">Total Submission</p>
            </div>
        </div>

        <div>
            <flux:button variant="primary" icon="squares-plus" :href="route('admin.quizzes.index')" wire:navigate class="transition hover:-translate-y-0.5">
                Kelola Quiz
            </flux:button>
        </div>
    @else
        <p class="text-sm text-zinc-500">Ini progress quiz kamu sejauh ini.</p>

        <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
            <div class="rounded-2xl border border-zinc-200 p-5 transition duration-200 hover:-translate-y-1 hover:shadow-lg dark:border-zinc-700">
                <div class="flex size-10 items-center justify-center rounded-xl bg-blue-100 text-accent dark:bg-blue-950">
                    <flux:icon.clipboard-document-list class="size-5" />
                </div>
                <p class="mt-4 font-display text-2xl font-extrabold text-zinc-900 dark:text-white">{{ $this->participantStats['available_quizzes'] }}</p>
                <p class="mt-0.5 text-sm font-semibold text-zinc-500 dark:text-zinc-400">Quiz Tersedia</p>
            </div>
            <div class="rounded-2xl border border-zinc-200 p-5 transition duration-200 hover:-translate-y-1 hover:shadow-lg dark:border-zinc-700">
                <div class="flex size-10 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600 dark:bg-emerald-950">
                    <flux:icon.check-circle class="size-5" />
                </div>
                <p class="mt-4 font-display text-2xl font-extrabold text-zinc-900 dark:text-white">{{ $this->participantStats['completed_attempts'] }}</p>
                <p class="mt-0.5 text-sm font-semibold text-zinc-500 dark:text-zinc-400">Sudah Dikerjakan</p>
            </div>
            <div class="animate-soft-pulse rounded-2xl border border-zinc-200 p-5 transition duration-200 hover:-translate-y-1 hover:shadow-lg dark:border-zinc-700">
                <div class="flex size-10 items-center justify-center rounded-xl bg-amber-100 text-amber-600 dark:bg-amber-950">
                    <flux:icon.chart-bar class="size-5" />
                </div>
                <p class="mt-4 font-display text-2xl font-extrabold text-zinc-900 dark:text-white">
                    {{ $this->participantStats['average_score'] !== null ? round($this->participantStats['average_score']).'%' : '—' }}
                </p>
                <p class="mt-0.5 text-sm font-semibold text-zinc-500 dark:text-zinc-400">Rata-rata Skor</p>
            </div>
        </div>

        @if ($this->inProgressAttempt)
            <div class="flex flex-col items-start justify-between gap-4 rounded-2xl bg-linear-to-br from-accent to-blue-900 p-6 sm:flex-row sm:items-center">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wide text-blue-100">Lanjutkan Belajar</p>
                    <p class="mt-1 font-display text-lg font-extrabold text-white">{{ $this->inProgressAttempt->quiz->title }} — belum selesai</p>
                </div>
                <flux:button :href="route('quizzes.attempt', $this->inProgressAttempt->quiz)" wire:navigate class="!bg-white !text-accent shrink-0 transition hover:!bg-blue-50 hover:-translate-y-0.5">
                    Lanjutkan Quiz
                </flux:button>
            </div>
        @endif

        <div class="flex flex-wrap gap-3">
            <flux:button variant="primary" icon="pencil-square" :href="route('quizzes.index')" wire:navigate class="transition hover:-translate-y-0.5">
                Kerjakan Quiz
            </flux:button>
            <flux:button icon="clock" :href="route('quizzes.history')" wire:navigate class="transition hover:-translate-y-0.5">
                Riwayat Saya
            </flux:button>
        </div>

        @if ($this->recentAttempts->isNotEmpty())
            <div>
                <flux:heading size="lg">Riwayat Terbaru</flux:heading>
                <div class="mt-3 divide-y divide-zinc-200 rounded-2xl border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
                    @foreach ($this->recentAttempts as $attempt)
                        <div class="flex items-center justify-between px-5 py-4 transition hover:bg-zinc-50 dark:hover:bg-zinc-800/50" wire:key="recent-{{ $attempt->id }}">
                            <div>
                                <p class="text-sm font-bold text-zinc-900 dark:text-white">{{ $attempt->quiz->title }}</p>
                                <p class="mt-0.5 text-xs text-zinc-400">{{ $attempt->submitted_at->format('d M Y') }}</p>
                            </div>
                            @php $passed = $attempt->quiz->passing_score === null || $attempt->score >= $attempt->quiz->passing_score; @endphp
                            <flux:badge :color="$passed ? 'green' : 'red'" size="sm">
                                {{ $passed ? 'Lulus' : 'Belum Lulus' }} · {{ $attempt->score }}%
                            </flux:badge>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    @endif
</div>
