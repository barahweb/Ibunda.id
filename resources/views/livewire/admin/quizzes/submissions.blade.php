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
        <flux:heading size="xl">Hasil — {{ $quiz->title }}</flux:heading>
        <flux:subheading>Daftar peserta yang sudah mengerjakan quiz ini.</flux:subheading>
    </div>

    <div class="grid grid-cols-3 gap-4">
        <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
            <p class="text-xs uppercase text-zinc-500 dark:text-zinc-400">Total Selesai</p>
            <p class="mt-1 text-2xl font-bold text-zinc-900 dark:text-white">{{ $this->stats['total'] }}</p>
        </div>
        <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
            <p class="text-xs uppercase text-zinc-500 dark:text-zinc-400">Rata-rata Skor</p>
            <p class="mt-1 text-2xl font-bold text-zinc-900 dark:text-white">{{ $this->stats['average_score'] !== null ? "{$this->stats['average_score']}%" : '—' }}</p>
        </div>
        <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
            <p class="text-xs uppercase text-zinc-500 dark:text-zinc-400">Lulus</p>
            <p class="mt-1 text-2xl font-bold text-zinc-900 dark:text-white">{{ $this->stats['passed'] !== null ? "{$this->stats['passed']} / {$this->stats['total']}" : '—' }}</p>
        </div>
    </div>

    <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
        <table class="w-full text-left text-sm">
            <thead class="bg-zinc-50 text-xs uppercase text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                <tr>
                    <th class="px-4 py-3">Peserta</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Skor</th>
                    <th class="px-4 py-3">Submit</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($this->attempts as $attempt)
                    <tr wire:key="submission-{{ $attempt->id }}">
                        <td class="px-4 py-3 font-medium text-zinc-900 dark:text-white">{{ $attempt->user->name }}</td>
                        <td class="px-4 py-3">
                            <flux:badge :color="$attempt->isCompleted() ? 'green' : 'zinc'" size="sm">
                                {{ $attempt->isCompleted() ? 'Selesai' : 'Belum selesai' }}
                            </flux:badge>
                        </td>
                        <td class="px-4 py-3 text-zinc-500 dark:text-zinc-400">
                            {{ $attempt->isCompleted() ? "{$attempt->score}%" : '—' }}
                        </td>
                        <td class="px-4 py-3 text-zinc-500 dark:text-zinc-400">
                            {{ $attempt->submitted_at?->format('d M Y H:i') ?? '—' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-8 text-center text-zinc-500 dark:text-zinc-400">
                            Belum ada peserta yang mengerjakan quiz ini.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $this->attempts->links() }}
</div>
