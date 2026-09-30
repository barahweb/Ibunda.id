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
    public function submissionsTrend(): array
    {
        $counts = QuizAttempt::query()
            ->where('status', QuizAttempt::STATUS_COMPLETED)
            ->whereBetween('submitted_at', [now()->subDays(13)->startOfDay(), now()->endOfDay()])
            ->get()
            ->groupBy(fn (QuizAttempt $attempt) => $attempt->submitted_at->toDateString())
            ->map->count();

        return collect(range(13, 0))
            ->map(function (int $daysAgo) use ($counts) {
                $date = now()->subDays($daysAgo);

                return [
                    'label' => $date->translatedFormat('d M'),
                    'count' => $counts->get($date->toDateString(), 0),
                ];
            })
            ->all();
    }

    #[Computed]
    public function topQuizzes()
    {
        return Quiz::query()
            ->withCount(['attempts as completed_attempts_count' => fn ($query) => $query->where('status', QuizAttempt::STATUS_COMPLETED)])
            ->orderByDesc('completed_attempts_count')
            ->limit(5)
            ->get()
            ->filter(fn (Quiz $quiz) => $quiz->completed_attempts_count > 0)
            ->values();
    }

    #[Computed]
    public function passFailBreakdown(): array
    {
        $attempts = QuizAttempt::query()
            ->where('status', QuizAttempt::STATUS_COMPLETED)
            ->whereHas('quiz', fn ($query) => $query->whereNotNull('passing_score'))
            ->with('quiz')
            ->get();

        $passed = $attempts->filter(fn (QuizAttempt $attempt) => $attempt->score >= $attempt->quiz->passing_score)->count();

        return [
            'passed' => $passed,
            'failed' => $attempts->count() - $passed,
            'total' => $attempts->count(),
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
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <flux:heading size="xl">Halo, {{ Auth::user()->name }}!</flux:heading>
            <flux:badge :color="$this->isAdmin ? 'blue' : 'zinc'" size="sm">{{ $this->isAdmin ? 'Admin' : 'Peserta' }}</flux:badge>
        </div>

        <div
            x-data="{
                now: new Date(),
                pad(n) { return n.toString().padStart(2, '0'); },
            }"
            x-init="setInterval(() => now = new Date(), 1000)"
            class="flex items-center gap-2 rounded-full border border-zinc-200 bg-white px-3.5 py-1.5 text-sm font-semibold text-zinc-600 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300"
        >
            <flux:icon.clock class="size-4 text-accent" />
            <span x-text="now.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })"></span>
            <span class="text-zinc-300 dark:text-zinc-600">&bull;</span>
            <span x-text="`${pad(now.getHours())}:${pad(now.getMinutes())}:${pad(now.getSeconds())}`"></span>
        </div>
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

        <div>
            <flux:heading size="lg" class="font-display">Analitik</flux:heading>
            <flux:subheading>Ringkasan aktivitas peserta.</flux:subheading>
        </div>

        @if ($this->adminStats['total_submissions'] === 0)
            <div class="rounded-2xl border border-dashed border-zinc-300 p-10 text-center dark:border-zinc-700">
                <flux:icon.chart-bar class="mx-auto size-8 text-zinc-300" />
                <p class="mt-3 text-sm text-zinc-500 dark:text-zinc-400">Belum ada data submission buat ditampilkan grafiknya.</p>
            </div>
        @else
            {{-- Tren submission 14 hari terakhir --}}
            <div class="rounded-2xl border border-zinc-200 p-5 dark:border-zinc-700">
                <p class="text-sm font-bold text-zinc-900 dark:text-white">Tren Submission — 14 Hari Terakhir</p>

                @php
                    $trend = $this->submissionsTrend;
                    $maxCount = max(1, max(array_column($trend, 'count')));
                    $chartW = 1200;
                    $chartH = 200;
                    $padX = 10;
                    $padTop = 14;
                    $padBottom = 10;
                    $step = ($chartW - $padX * 2) / (count($trend) - 1);

                    $points = collect($trend)->map(function ($point, $i) use ($step, $padX, $padTop, $chartH, $padBottom, $maxCount, $chartW) {
                        $x = round($padX + $i * $step, 1);

                        return [
                            'x' => $x,
                            'xPct' => max(3, min(97, round($x / $chartW * 100, 2))),
                            'y' => round($padTop + (1 - $point['count'] / $maxCount) * ($chartH - $padTop - $padBottom), 1),
                            'label' => $point['label'],
                            'count' => $point['count'],
                        ];
                    });

                    $linePath = $points->map(fn ($p, $i) => ($i === 0 ? 'M' : 'L')." {$p['x']},{$p['y']}")->implode(' ');
                    $areaPath = $linePath." L {$points->last()['x']},{$chartH} L {$points->first()['x']},{$chartH} Z";
                @endphp

                <svg viewBox="0 0 {{ $chartW }} {{ $chartH }}" width="{{ $chartW }}" height="{{ $chartH }}" class="mt-4 h-auto w-full">
                    <defs>
                        <linearGradient id="trendFill" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="#2563eb" stop-opacity="0.25" />
                            <stop offset="100%" stop-color="#2563eb" stop-opacity="0" />
                        </linearGradient>
                    </defs>

                    <path d="{{ $areaPath }}" fill="url(#trendFill)" />
                    <path d="{{ $linePath }}" fill="none" stroke="#2563eb" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />

                    @foreach ($points as $p)
                        <g class="group cursor-pointer">
                            <circle cx="{{ $p['x'] }}" cy="{{ $p['y'] }}" r="18" fill="transparent" />
                            <circle cx="{{ $p['x'] }}" cy="{{ $p['y'] }}" r="4" fill="#2563eb" class="pointer-events-none" />
                            <foreignObject
                                x="{{ max(0, min($chartW - 120, $p['x'] - 60)) }}"
                                y="{{ max(0, $p['y'] - 54) }}"
                                width="120" height="44"
                                class="pointer-events-none opacity-0 transition duration-150 group-hover:opacity-100"
                                style="overflow: visible;"
                            >
                                <div xmlns="http://www.w3.org/1999/xhtml" class="mx-auto w-fit rounded-lg bg-zinc-900 px-3 py-1.5 text-center text-xs font-semibold whitespace-nowrap text-white shadow-lg">
                                    {{ $p['count'] }} submission
                                    <div class="text-[10px] font-normal text-zinc-400">{{ $p['label'] }}</div>
                                </div>
                            </foreignObject>
                        </g>
                    @endforeach
                </svg>

                <div class="relative mt-1 h-9 text-xs">
                    @foreach ($points as $i => $p)
                        @if ($i % 3 === 0 || $i === count($points) - 1)
                            <div class="absolute flex w-max -translate-x-1/2 flex-col items-center" style="left: {{ $p['xPct'] }}%">
                                <span class="font-bold whitespace-nowrap text-accent">{{ $p['count'] }}</span>
                                <span class="whitespace-nowrap text-zinc-400">{{ $p['label'] }}</span>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>

            <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
                {{-- Quiz terpopuler --}}
                <div class="rounded-2xl border border-zinc-200 p-5 dark:border-zinc-700">
                    <p class="text-sm font-bold text-zinc-900 dark:text-white">Quiz Terpopuler</p>

                    @if ($this->topQuizzes->isEmpty())
                        <p class="mt-4 text-sm text-zinc-400">Belum ada submission.</p>
                    @else
                        @php $maxAttempts = max(1, $this->topQuizzes->max('completed_attempts_count')); @endphp
                        <div class="mt-4 flex flex-col gap-3">
                            @foreach ($this->topQuizzes as $quiz)
                                <div
                                    wire:key="top-quiz-{{ $quiz->id }}"
                                    class="group -mx-2 rounded-lg px-2 py-1 transition hover:bg-blue-50 dark:hover:bg-blue-950/40"
                                    title="{{ $quiz->title }}: {{ $quiz->completed_attempts_count }} submission"
                                >
                                    <div class="flex items-center justify-between text-xs">
                                        <span class="truncate pr-2 font-semibold text-zinc-700 dark:text-zinc-300">{{ $quiz->title }}</span>
                                        <span class="shrink-0 font-bold text-accent">{{ $quiz->completed_attempts_count }} submission</span>
                                    </div>
                                    <div class="mt-1.5 h-2 w-full overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                                        <div class="h-full rounded-full bg-accent transition-all group-hover:bg-blue-700" style="width: {{ ($quiz->completed_attempts_count / $maxAttempts) * 100 }}%"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Tingkat kelulusan --}}
                <div class="rounded-2xl border border-zinc-200 p-5 dark:border-zinc-700">
                    <p class="text-sm font-bold text-zinc-900 dark:text-white">Tingkat Kelulusan</p>

                    @php
                        $breakdown = $this->passFailBreakdown;
                        $circumference = 2 * M_PI * 40;
                        $passedFraction = $breakdown['total'] > 0 ? $breakdown['passed'] / $breakdown['total'] : 0;
                        $passedLength = $circumference * $passedFraction;
                    @endphp

                    @if ($breakdown['total'] === 0)
                        <p class="mt-4 text-sm text-zinc-400">Belum ada quiz dengan nilai lulus yang diselesaikan.</p>
                    @else
                        <div class="mt-2 flex items-center gap-6">
                            <svg viewBox="0 0 100 100" class="size-28 shrink-0 -rotate-90">
                                <circle cx="50" cy="50" r="40" fill="none" stroke="currentColor" class="text-red-200 dark:text-red-950" stroke-width="14">
                                    <title>Belum Lulus: {{ $breakdown['failed'] }} attempt</title>
                                </circle>
                                <circle
                                    cx="50" cy="50" r="40" fill="none" stroke="currentColor" class="text-emerald-500"
                                    stroke-width="14" stroke-linecap="round"
                                    stroke-dasharray="{{ $passedLength }} {{ $circumference }}"
                                >
                                    <title>Lulus: {{ $breakdown['passed'] }} attempt</title>
                                </circle>
                            </svg>
                            <div>
                                <p class="font-display text-2xl font-extrabold text-zinc-900 dark:text-white">{{ round($passedFraction * 100) }}%</p>
                                <p class="text-xs text-zinc-400">dari {{ $breakdown['total'] }} attempt selesai</p>
                                <div class="mt-2 flex flex-col gap-1 text-xs">
                                    <span class="flex items-center gap-1.5 text-zinc-600 dark:text-zinc-400">
                                        <span class="size-2 rounded-full bg-emerald-500"></span>
                                        Lulus ({{ $breakdown['passed'] }})
                                    </span>
                                    <span class="flex items-center gap-1.5 text-zinc-600 dark:text-zinc-400">
                                        <span class="size-2 rounded-full bg-red-200 dark:bg-red-950"></span>
                                        Belum Lulus ({{ $breakdown['failed'] }})
                                    </span>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        @endif
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
