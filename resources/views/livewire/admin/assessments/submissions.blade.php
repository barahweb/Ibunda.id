<?php

use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;

    public Assessment $assessment;

    public function mount(Assessment $assessment): void
    {
        $this->authorize('view', $assessment);
        $this->authorize('viewAny', AssessmentAttempt::class);

        $this->assessment = $assessment;
    }

    #[Computed]
    public function attempts()
    {
        return $this->assessment->attempts()
            ->with('user')
            ->latest('started_at')
            ->paginate(15);
    }

    #[Computed]
    public function completedCount(): int
    {
        return $this->assessment->attempts()->where('status', AssessmentAttempt::STATUS_COMPLETED)->count();
    }
}; ?>

<div class="flex flex-col gap-6">
    <div>
        <flux:link :href="route('admin.assessments.index')" wire:navigate class="text-sm">&larr; Kembali ke Kelola Tes Kepribadian</flux:link>
    </div>

    <div>
        <flux:heading size="xl" class="font-display">Hasil: {{ $assessment->title }}</flux:heading>
        <flux:subheading>Daftar peserta yang sudah mengerjakan tes ini.</flux:subheading>
    </div>

    <div class="rounded-2xl border border-zinc-200 p-5 dark:border-zinc-700">
        <p class="font-display text-2xl font-extrabold text-zinc-900 dark:text-white">{{ $this->completedCount }}</p>
        <p class="mt-0.5 text-sm font-semibold text-zinc-500 dark:text-zinc-400">Total Selesai</p>
    </div>

    <div class="overflow-x-auto rounded-2xl border border-zinc-200 dark:border-zinc-700">
        <table class="w-full text-left text-sm">
            <thead class="bg-zinc-50 text-xs uppercase text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                <tr>
                    <th class="px-5 py-3.5">Peserta</th>
                    <th class="px-5 py-3.5">Status</th>
                    <th class="px-5 py-3.5">Tipe</th>
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
                        <td class="px-5 py-4 font-display font-bold tracking-widest text-accent">{{ $attempt->result_type ?? '—' }}</td>
                        <td class="px-5 py-4 text-zinc-500 dark:text-zinc-400">{{ $attempt->submitted_at?->format('d M Y H:i') ?? '—' }}</td>
                        <td class="px-5 py-4">
                            <div class="flex justify-center">
                                @if ($attempt->isCompleted())
                                    <flux:button size="sm" variant="ghost" :href="route('assessments.attempts.result', $attempt)" wire:navigate>Lihat Detail</flux:button>
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
                            <p class="mt-3 text-sm text-zinc-500 dark:text-zinc-400">Belum ada peserta yang mengerjakan tes ini.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $this->attempts->links() }}
</div>
