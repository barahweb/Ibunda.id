<?php

use App\Models\AssessmentAttempt;
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
        return AssessmentAttempt::query()
            ->where('user_id', Auth::id())
            ->with(['assessment' => fn ($query) => $query->withCount('questions')])
            ->withCount(['answers as answered_count' => fn ($query) => $query->whereNotNull('value')->whereHas('question')])
            ->latest('started_at')
            ->paginate(10);
    }
}; ?>

<div class="flex flex-col gap-6">
    <div>
        <flux:heading size="xl" class="font-display">Riwayat Tes Saya</flux:heading>
        <flux:subheading>Semua tes kepribadian yang pernah kamu kerjakan.</flux:subheading>
    </div>

    <div class="overflow-x-auto rounded-2xl border border-zinc-200 dark:border-zinc-700">
        <table class="w-full text-left text-sm">
            <thead class="bg-zinc-50 text-xs uppercase text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                <tr>
                    <th class="px-5 py-3.5">Tes</th>
                    <th class="px-5 py-3.5">Status</th>
                    <th class="px-5 py-3.5">Tipe</th>
                    <th class="px-5 py-3.5">Mulai</th>
                    <th class="px-5 py-3.5">Progress</th>
                    <th class="px-5 py-3.5 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($this->attempts as $attempt)
                    <tr wire:key="assessment-attempt-{{ $attempt->id }}" class="transition hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                        <td class="px-5 py-4 font-semibold text-zinc-900 dark:text-white">{{ $attempt->assessment->title }}</td>
                        <td class="px-5 py-4">
                            <flux:badge :color="$attempt->isCompleted() ? 'green' : 'zinc'" size="sm">
                                {{ $attempt->isCompleted() ? 'Selesai' : 'Belum selesai' }}
                            </flux:badge>
                        </td>
                        <td class="px-5 py-4 font-display font-bold tracking-widest text-accent">{{ $attempt->result_type ?? '—' }}</td>
                        <td class="px-5 py-4 text-zinc-500 dark:text-zinc-400">{{ $attempt->started_at->format('d M Y H:i') }}</td>
                        <td class="px-5 py-4">
                            @if ($attempt->isCompleted())
                                <span class="text-zinc-300">—</span>
                            @else
                                @php
                                    $total = $attempt->assessment->questions_count;
                                    $answered = min($attempt->answered_count, $total);
                                    $percentage = $total > 0 ? round($answered / $total * 100) : 0;
                                @endphp
                                <div class="min-w-28">
                                    <p class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">{{ $answered }}/{{ $total }} terjawab</p>
                                    <div class="mt-1.5 h-1.5 w-full overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                                        <div class="h-full rounded-full bg-accent" style="width: {{ $percentage }}%"></div>
                                    </div>
                                </div>
                            @endif
                        </td>
                        <td class="px-5 py-4">
                            <div class="flex justify-center">
                                @if ($attempt->isCompleted())
                                    <flux:button size="sm" variant="ghost" :href="route('assessments.attempts.result', $attempt)" wire:navigate>Lihat Hasil</flux:button>
                                @else
                                    <flux:button size="sm" variant="ghost" :href="route('assessments.attempt', $attempt->assessment)" wire:navigate>Lanjutkan</flux:button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-5 py-14 text-center">
                            <flux:icon.clock class="mx-auto size-8 text-zinc-300" />
                            <p class="mt-3 text-sm text-zinc-500 dark:text-zinc-400">Belum ada riwayat. Yuk mulai kerjakan tes.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $this->attempts->links() }}
</div>
