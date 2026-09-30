<?php

use App\Exports\QuizReportExport;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;

new #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $quizId = '';

    #[Url]
    public string $status = '';

    public function mount(): void
    {
        $this->authorize('viewAny', QuizAttempt::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedQuizId(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    /**
     * Query dasar (belum dipaginate) yang udah kena filter cari & quiz,
     * dipakai bareng buat tabel maupun kartu statistik biar konsisten.
     */
    private function baseQuery()
    {
        return QuizAttempt::query()
            ->join('quizzes', 'quizzes.id', '=', 'quiz_attempts.quiz_id')
            ->where('quiz_attempts.status', QuizAttempt::STATUS_COMPLETED)
            ->when(
                $this->search,
                fn ($query) => $query->whereHas(
                    'user',
                    fn ($userQuery) => $userQuery
                        ->where('name', 'like', "%{$this->search}%")
                        ->orWhere('email', 'like', "%{$this->search}%")
                )
            )
            ->when($this->quizId, fn ($query) => $query->where('quiz_attempts.quiz_id', $this->quizId));
    }

    /**
     * baseQuery() + filter status lulus/belum, dipakai bareng buat tabel maupun export.
     */
    private function filteredQuery()
    {
        return $this->baseQuery()
            ->select('quiz_attempts.*')
            ->with(['user', 'quiz'])
            ->when(
                $this->status === 'passed',
                fn ($query) => $query->whereNotNull('quizzes.passing_score')->whereColumn('quiz_attempts.score', '>=', 'quizzes.passing_score')
            )
            ->when(
                $this->status === 'failed',
                fn ($query) => $query->whereNotNull('quizzes.passing_score')->whereColumn('quiz_attempts.score', '<', 'quizzes.passing_score')
            )
            ->latest('quiz_attempts.submitted_at');
    }

    #[Computed]
    public function attempts()
    {
        return $this->filteredQuery()->paginate(15);
    }

    public function export(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $attempts = $this->filteredQuery()->get();

        return response()->streamDownload(function () use ($attempts) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['Peserta', 'Email', 'Quiz', 'Skor', 'Status', 'Tanggal Submit']);

            foreach ($attempts as $attempt) {
                fputcsv($handle, [
                    $attempt->user->name,
                    $attempt->user->email,
                    $attempt->quiz->title,
                    $attempt->score,
                    $attempt->quiz->passing_score === null ? '-' : ($attempt->hasPassed() ? 'Lulus' : 'Belum Lulus'),
                    $attempt->submitted_at->format('Y-m-d H:i'),
                ]);
            }

            fclose($handle);
        }, 'laporan-quiz-'.now()->format('Y-m-d-His').'.csv');
    }

    public function exportExcel(): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        return Excel::download(
            new QuizReportExport($this->filteredQuery()->get()),
            'laporan-quiz-'.now()->format('Y-m-d-His').'.xlsx',
        );
    }

    #[Computed]
    public function stats(): array
    {
        $total = (clone $this->baseQuery())->count();
        $averageScore = $total > 0 ? round((clone $this->baseQuery())->avg('quiz_attempts.score')) : null;

        $gradable = (clone $this->baseQuery())->whereNotNull('quizzes.passing_score');
        $gradableTotal = (clone $gradable)->count();
        $passed = (clone $gradable)->whereColumn('quiz_attempts.score', '>=', 'quizzes.passing_score')->count();

        return [
            'total' => $total,
            'average_score' => $averageScore,
            'passed' => $passed,
            'gradable_total' => $gradableTotal,
        ];
    }

    #[Computed]
    public function quizzes()
    {
        return Quiz::query()->orderBy('title')->get(['id', 'title']);
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" class="font-display">Laporan</flux:heading>
            <flux:subheading>Semua hasil quiz dari seluruh peserta, dalam satu tempat.</flux:subheading>
        </div>

        <div class="flex gap-2">
            <flux:button icon="arrow-down-tray" wire:click="export">Export CSV</flux:button>
            <flux:button icon="arrow-down-tray" wire:click="exportExcel">Export Excel</flux:button>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
        <div class="rounded-2xl border border-zinc-200 p-5 dark:border-zinc-700">
            <div class="flex size-10 items-center justify-center rounded-xl bg-blue-100 text-accent dark:bg-blue-950">
                <flux:icon.clipboard-document-list class="size-5" />
            </div>
            <p class="mt-4 font-display text-2xl font-extrabold text-zinc-900 dark:text-white">{{ $this->stats['total'] }}</p>
            <p class="mt-0.5 text-sm font-semibold text-zinc-500 dark:text-zinc-400">Total Attempt</p>
        </div>
        <div class="rounded-2xl border border-zinc-200 p-5 dark:border-zinc-700">
            <div class="flex size-10 items-center justify-center rounded-xl bg-amber-100 text-amber-600 dark:bg-amber-950">
                <flux:icon.chart-bar class="size-5" />
            </div>
            <p class="mt-4 font-display text-2xl font-extrabold text-zinc-900 dark:text-white">
                {{ $this->stats['average_score'] !== null ? "{$this->stats['average_score']}%" : '—' }}
            </p>
            <p class="mt-0.5 text-sm font-semibold text-zinc-500 dark:text-zinc-400">Rata-rata Skor</p>
        </div>
        <div class="rounded-2xl border border-zinc-200 p-5 dark:border-zinc-700">
            <div class="flex size-10 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600 dark:bg-emerald-950">
                <flux:icon.check-circle class="size-5" />
            </div>
            <p class="mt-4 font-display text-2xl font-extrabold text-zinc-900 dark:text-white">
                {{ $this->stats['gradable_total'] > 0 ? "{$this->stats['passed']} / {$this->stats['gradable_total']}" : '—' }}
            </p>
            <p class="mt-0.5 text-sm font-semibold text-zinc-500 dark:text-zinc-400">Lulus (quiz berpenilaian)</p>
        </div>
    </div>

    <div class="flex flex-wrap gap-3">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Cari nama atau email peserta…" class="max-w-sm" />

        <flux:select wire:model.live="quizId" placeholder="Semua quiz" class="max-w-xs">
            <flux:select.option value="">Semua quiz</flux:select.option>
            @foreach ($this->quizzes as $quiz)
                <flux:select.option value="{{ $quiz->id }}">{{ $quiz->title }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="status" placeholder="Semua status" class="max-w-xs">
            <flux:select.option value="">Semua status</flux:select.option>
            <flux:select.option value="passed">Lulus</flux:select.option>
            <flux:select.option value="failed">Belum Lulus</flux:select.option>
        </flux:select>
    </div>

    <div class="overflow-x-auto rounded-2xl border border-zinc-200 dark:border-zinc-700">
        <table class="w-full text-left text-sm">
            <thead class="bg-zinc-50 text-xs uppercase text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                <tr>
                    <th class="px-5 py-3.5">Peserta</th>
                    <th class="px-5 py-3.5">Quiz</th>
                    <th class="px-5 py-3.5">Skor</th>
                    <th class="px-5 py-3.5">Status</th>
                    <th class="px-5 py-3.5">Submit</th>
                    <th class="px-5 py-3.5 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($this->attempts as $attempt)
                    <tr wire:key="report-{{ $attempt->id }}" class="transition hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                        <td class="px-5 py-4">
                            <p class="font-semibold text-zinc-900 dark:text-white">{{ $attempt->user->name }}</p>
                            <p class="text-xs text-zinc-400">{{ $attempt->user->email }}</p>
                        </td>
                        <td class="px-5 py-4 text-zinc-500 dark:text-zinc-400">{{ $attempt->quiz->title }}</td>
                        <td class="px-5 py-4 font-semibold text-zinc-900 dark:text-white">{{ $attempt->score }}%</td>
                        <td class="px-5 py-4">
                            @if ($attempt->quiz->passing_score === null)
                                <span class="text-zinc-300">—</span>
                            @else
                                <flux:badge :color="$attempt->hasPassed() ? 'green' : 'red'" size="sm">
                                    {{ $attempt->hasPassed() ? 'Lulus' : 'Belum Lulus' }}
                                </flux:badge>
                            @endif
                        </td>
                        <td class="px-5 py-4 text-zinc-500 dark:text-zinc-400">{{ $attempt->submitted_at->format('d M Y H:i') }}</td>
                        <td class="px-5 py-4">
                            <div class="flex justify-center">
                                <flux:button size="sm" variant="ghost" :href="route('quizzes.attempts.result', $attempt)" wire:navigate>
                                    Lihat Detail
                                </flux:button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-5 py-14 text-center">
                            <flux:icon.chart-bar-square class="mx-auto size-8 text-zinc-300" />
                            <p class="mt-3 text-sm text-zinc-500 dark:text-zinc-400">
                                Gak ada hasil yang cocok sama filter ini.
                            </p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $this->attempts->links() }}
</div>
