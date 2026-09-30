<?php

use App\Models\Quiz;
use App\Models\QuizAttempt;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;

    public Quiz $quiz;

    public function mount(Quiz $quiz): void
    {
        $this->authorize('view', $quiz);

        $this->quiz = $quiz;
    }

    #[Computed]
    public function attempts()
    {
        return $this->quiz->attempts()
            ->with('user')
            ->latest('started_at')
            ->paginate(15);
    }

    #[Computed]
    public function stats()
    {
        $completed = $this->quiz->attempts()->where('status', QuizAttempt::STATUS_COMPLETED);

        $total = (clone $completed)->count();
        $averageScore = $total > 0 ? round((clone $completed)->avg('score')) : null;
        $passed = $this->quiz->passing_score !== null
            ? (clone $completed)->where('score', '>=', $this->quiz->passing_score)->count()
            : null;

        return [
            'total' => $total,
            'average_score' => $averageScore,
            'passed' => $passed,
        ];
    }
}; ?>

<div class="flex flex-col gap-6">
    <div>
        <flux:link :href="route('admin.quizzes.index')" wire:navigate class="text-sm">&larr; Kembali ke Quiz Management</flux:link>
    </div>

    <div>
        <flux:heading size="xl" class="font-display">Hasil — {{ $quiz->title }}</flux:heading>
        <flux:subheading>Daftar peserta yang sudah mengerjakan quiz ini.</flux:subheading>
    </div>

    <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
        <div class="rounded-2xl border border-zinc-200 p-5 transition duration-200 hover:-translate-y-1 hover:shadow-lg dark:border-zinc-700">
            <div class="flex size-10 items-center justify-center rounded-xl bg-blue-100 text-accent dark:bg-blue-950">
                <flux:icon.users class="size-5" />
            </div>
            <p class="mt-4 font-display text-2xl font-extrabold text-zinc-900 dark:text-white">{{ $this->stats['total'] }}</p>
            <p class="mt-0.5 text-sm font-semibold text-zinc-500 dark:text-zinc-400">Total Selesai</p>
        </div>
        <div class="rounded-2xl border border-zinc-200 p-5 transition duration-200 hover:-translate-y-1 hover:shadow-lg dark:border-zinc-700">
            <div class="flex size-10 items-center justify-center rounded-xl bg-amber-100 text-amber-600 dark:bg-amber-950">
                <flux:icon.chart-bar class="size-5" />
            </div>
            <p class="mt-4 font-display text-2xl font-extrabold text-zinc-900 dark:text-white">{{ $this->stats['average_score'] !== null ? "{$this->stats['average_score']}%" : '—' }}</p>
            <p class="mt-0.5 text-sm font-semibold text-zinc-500 dark:text-zinc-400">Rata-rata Skor</p>
        </div>
        <div class="rounded-2xl border border-zinc-200 p-5 transition duration-200 hover:-translate-y-1 hover:shadow-lg dark:border-zinc-700">
            <div class="flex size-10 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600 dark:bg-emerald-950">
                <flux:icon.check-circle class="size-5" />
            </div>
            <p class="mt-4 font-display text-2xl font-extrabold text-zinc-900 dark:text-white">{{ $this->stats['passed'] !== null ? "{$this->stats['passed']} / {$this->stats['total']}" : '—' }}</p>
            <p class="mt-0.5 text-sm font-semibold text-zinc-500 dark:text-zinc-400">Lulus</p>
        </div>
    </div>

    <div class="overflow-x-auto rounded-2xl border border-zinc-200 dark:border-zinc-700">
        <table class="w-full text-left text-sm">
            <thead class="bg-zinc-50 text-xs uppercase text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                <tr>
                    <th class="px-5 py-3.5">Peserta</th>
                    <th class="px-5 py-3.5">Status</th>
                    <th class="px-5 py-3.5">Skor</th>
                    <th class="px-5 py-3.5">Submit</th>
                    <th class="px-5 py-3.5 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($this->attempts as $attempt)
                    <tr wire:key="submission-{{ $attempt->id }}" class="transition hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                        <td class="px-5 py-4 font-semibold text-zinc-900 dark:text-white">{{ $attempt->user->name }}</td>
                        <td class="px-5 py-4">
                            <flux:badge :color="$attempt->isCompleted() ? 'green' : 'zinc'" size="sm">
                                {{ $attempt->isCompleted() ? 'Selesai' : 'Belum selesai' }}
                            </flux:badge>
                        </td>
                        <td class="px-5 py-4 text-zinc-500 dark:text-zinc-400">
                            {{ $attempt->isCompleted() ? "{$attempt->score}%" : '—' }}
                        </td>
                        <td class="px-5 py-4 text-zinc-500 dark:text-zinc-400">
                            {{ $attempt->submitted_at?->format('d M Y H:i') ?? '—' }}
                        </td>
                        <td class="px-5 py-4">
                            <div class="flex justify-center">
                                @if ($attempt->isCompleted())
                                    <flux:button size="sm" variant="ghost" :href="route('quizzes.attempts.result', $attempt)" wire:navigate>
                                        Lihat Detail
                                    </flux:button>
                                @else
                                    <span class="text-sm text-zinc-300">—</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-5 py-14 text-center">
                            <flux:icon.users class="mx-auto size-8 text-zinc-300" />
                            <p class="mt-3 text-sm text-zinc-500 dark:text-zinc-400">
                                Belum ada peserta yang mengerjakan quiz ini.
                            </p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $this->attempts->links() }}
</div>
