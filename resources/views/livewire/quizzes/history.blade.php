<?php

use App\Models\QuizAttempt;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;

    #[Computed]
    public function attempts()
    {
        return QuizAttempt::query()
            ->where('user_id', Auth::id())
            ->with('quiz')
            ->latest('started_at')
            ->paginate(10);
    }
}; ?>

<div class="flex flex-col gap-6">
    <div>
        <flux:heading size="xl">Riwayat Quiz Saya</flux:heading>
        <flux:subheading>Semua quiz yang pernah kamu kerjakan.</flux:subheading>
    </div>

    <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
        <table class="w-full text-left text-sm">
            <thead class="bg-zinc-50 text-xs uppercase text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                <tr>
                    <th class="px-4 py-3">Quiz</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Skor</th>
                    <th class="px-4 py-3">Mulai</th>
                    <th class="px-4 py-3 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($this->attempts as $attempt)
                    <tr wire:key="attempt-{{ $attempt->id }}">
                        <td class="px-4 py-3 font-medium text-zinc-900 dark:text-white">{{ $attempt->quiz->title }}</td>
                        <td class="px-4 py-3">
                            <flux:badge :color="$attempt->isCompleted() ? 'green' : 'zinc'" size="sm">
                                {{ $attempt->isCompleted() ? 'Selesai' : 'Belum selesai' }}
                            </flux:badge>
                        </td>
                        <td class="px-4 py-3 text-zinc-500 dark:text-zinc-400">
                            {{ $attempt->isCompleted() ? "{$attempt->score}%" : '—' }}
                        </td>
                        <td class="px-4 py-3 text-zinc-500 dark:text-zinc-400">{{ $attempt->started_at->format('d M Y H:i') }}</td>
                        <td class="px-4 py-3 text-right">
                            @if ($attempt->isCompleted())
                                <flux:button size="sm" variant="ghost" :href="route('quizzes.attempts.result', $attempt)" wire:navigate>
                                    Lihat Hasil
                                </flux:button>
                            @else
                                <flux:button size="sm" variant="ghost" :href="route('quizzes.attempt', $attempt->quiz)" wire:navigate>
                                    Lanjutkan
                                </flux:button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-zinc-500 dark:text-zinc-400">
                            Belum ada riwayat. Yuk mulai kerjakan quiz.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $this->attempts->links() }}
</div>
