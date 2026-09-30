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
            ->with(['quiz' => fn ($query) => $query->withCount('questions')])
            ->withCount(['answers as answered_count' => fn ($query) => $query->whereNotNull('selected_option_id')->whereHas('question')])
            ->latest('started_at')
            ->paginate(10);
    }
}; ?>

<div class="flex flex-col gap-6">
    <div>
        <flux:heading size="xl" class="font-display">Riwayat Quiz Saya</flux:heading>
        <flux:subheading>Semua quiz yang pernah kamu kerjakan.</flux:subheading>
    </div>

    <div class="overflow-x-auto rounded-2xl border border-zinc-200 dark:border-zinc-700">
        <table class="w-full text-left text-sm">
            <thead class="bg-zinc-50 text-xs uppercase text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                <tr>
                    <th class="px-5 py-3.5">Quiz</th>
                    <th class="px-5 py-3.5">Status</th>
                    <th class="px-5 py-3.5">Skor</th>
                    <th class="px-5 py-3.5">Mulai</th>
                    <th class="px-5 py-3.5">Sisa Waktu</th>
                    <th class="px-5 py-3.5">Progress</th>
                    <th class="px-5 py-3.5 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($this->attempts as $attempt)
                    <tr wire:key="attempt-{{ $attempt->id }}" class="transition hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                        <td class="px-5 py-4 font-semibold text-zinc-900 dark:text-white">{{ $attempt->quiz->title }}</td>
                        <td class="px-5 py-4">
                            <flux:badge :color="$attempt->isCompleted() ? 'green' : 'zinc'" size="sm">
                                {{ $attempt->isCompleted() ? 'Selesai' : 'Belum selesai' }}
                            </flux:badge>
                        </td>
                        <td class="px-5 py-4 text-zinc-500 dark:text-zinc-400">
                            {{ $attempt->isCompleted() ? "{$attempt->score}%" : '—' }}
                        </td>
                        <td class="px-5 py-4 text-zinc-500 dark:text-zinc-400">{{ $attempt->started_at->format('d M Y H:i') }}</td>
                        <td class="px-5 py-4">
                            @php $remaining = $attempt->isCompleted() ? null : $attempt->remainingSeconds(); @endphp

                            @if ($attempt->isCompleted())
                                <span class="text-zinc-300">—</span>
                            @elseif ($remaining === null)
                                <span class="text-zinc-400">Tanpa batas waktu</span>
                            @elseif ($remaining === 0)
                                <span class="font-semibold text-red-600">Waktu habis</span>
                            @else
                                <span
                                    x-data="{
                                        remaining: {{ $remaining }},
                                        get label() {
                                            const minutes = String(Math.floor(this.remaining / 60)).padStart(2, '0');
                                            const seconds = String(this.remaining % 60).padStart(2, '0');

                                            return `${minutes}:${seconds}`;
                                        },
                                    }"
                                    x-init="setInterval(() => { if (remaining > 0) remaining--; }, 1000)"
                                    class="font-semibold tabular-nums"
                                    :class="remaining <= 60 ? 'text-red-600' : 'text-zinc-900'"
                                    x-text="remaining > 0 ? label : 'Waktu habis'"
                                ></span>
                            @endif
                        </td>
                        <td class="px-5 py-4">
                            @if ($attempt->isCompleted())
                                <span class="text-zinc-300">—</span>
                            @else
                                @php
                                    $totalQuestions = $attempt->quiz->questions_count;
                                    $answered = min($attempt->answered_count, $totalQuestions);
                                    $percentage = $totalQuestions > 0 ? round($answered / $totalQuestions * 100) : 0;
                                @endphp

                                <div class="min-w-28">
                                    <p class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">{{ $answered }}/{{ $totalQuestions }} soal terjawab</p>
                                    <div class="mt-1.5 h-1.5 w-full overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                                        <div class="h-full rounded-full bg-accent transition-all" style="width: {{ $percentage }}%"></div>
                                    </div>
                                </div>
                            @endif
                        </td>
                        <td class="px-5 py-4">
                            <div class="flex justify-center">
                                @if ($attempt->isCompleted())
                                    <flux:button size="sm" variant="ghost" :href="route('quizzes.attempts.result', $attempt)" wire:navigate>
                                        Lihat Hasil
                                    </flux:button>
                                @else
                                    <flux:button size="sm" variant="ghost" :href="route('quizzes.attempt', $attempt->quiz)" wire:navigate>
                                        Lanjutkan
                                    </flux:button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-5 py-14 text-center">
                            <flux:icon.clock class="mx-auto size-8 text-zinc-300" />
                            <p class="mt-3 text-sm text-zinc-500 dark:text-zinc-400">Belum ada riwayat. Yuk mulai kerjakan quiz.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $this->attempts->links() }}
</div>
