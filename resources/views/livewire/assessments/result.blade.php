<?php

use App\Helper\OejtsScorer;
use App\Models\AssessmentAttempt;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public AssessmentAttempt $attempt;

    public function mount(AssessmentAttempt $attempt): void
    {
        $this->authorize('view', $attempt);

        abort_unless($attempt->isCompleted(), 404);

        $this->attempt = $attempt;
    }

    /**
     * Satu baris per dimensi, urutannya ngikutin OejtsScorer::DIMENSIONS (bukan urutan key
     * di kolom JSON, karena MySQL ngurutin ulang key JSON).
     *
     * @return array<int, array{dimension: string, left: string, right: string, sum: int, percent: int, letter: string}>
     */
    #[Computed]
    public function dimensions(): array
    {
        $labels = [
            'EI' => ['Extraversion', 'Introversion'],
            'SN' => ['Sensing', 'Intuition'],
            'TF' => ['Thinking', 'Feeling'],
            'JP' => ['Judging', 'Perceiving'],
        ];

        return collect(OejtsScorer::DIMENSIONS)
            ->map(function (string $dimension) use ($labels) {
                $sum = (int) ($this->attempt->dimension_scores[$dimension] ?? 0);

                return [
                    'dimension' => $dimension,
                    'left' => $labels[$dimension][0],
                    'right' => $labels[$dimension][1],
                    'sum' => $sum,
                    'percent' => (int) round((min(max($sum, 8), 40) - 8) / 32 * 100),
                    'letter' => OejtsScorer::letterForDimension($dimension, $sum),
                ];
            })
            ->all();
    }

    #[Computed]
    public function typeDescription(): ?array
    {
        return config('oejts.types.'.$this->attempt->result_type);
    }
}; ?>

<div class="flex flex-col gap-6">
    <div>
        <flux:link href="#" onclick="window.history.back(); return false;" class="text-sm">&larr; Kembali</flux:link>
    </div>

    <div class="rounded-2xl border border-zinc-200 p-10 text-center dark:border-zinc-700">
        <flux:heading size="xl" class="font-display">{{ $attempt->assessment->title }}</flux:heading>
        <p class="mt-2 font-display text-6xl font-extrabold tracking-widest text-accent">{{ $attempt->result_type }}</p>
        @if ($this->typeDescription)
            <p class="mt-2 text-lg font-semibold text-zinc-900 dark:text-white">{{ $this->typeDescription['name'] }}</p>
            <p class="mx-auto mt-2 max-w-xl text-sm text-zinc-500 dark:text-zinc-400">{{ $this->typeDescription['description'] }}</p>
        @endif
        <p class="mt-4 text-xs text-zinc-400">Dikerjakan oleh {{ $attempt->user->name }}, submit {{ $attempt->submitted_at->format('d M Y H:i') }}</p>
    </div>

    <div class="flex flex-col gap-4 rounded-2xl border border-zinc-200 p-5 dark:border-zinc-700">
        <flux:heading size="lg" class="font-display">Kecenderungan per dimensi</flux:heading>

        @foreach ($this->dimensions as $row)
            <div wire:key="dimension-{{ $row['dimension'] }}">
                <div class="flex items-center justify-between text-sm">
                    <span class="{{ $row['letter'] === substr($row['dimension'], 0, 1) ? 'font-bold text-accent' : 'text-zinc-500' }}">{{ $row['left'] }} ({{ substr($row['dimension'], 0, 1) }})</span>
                    <span class="{{ $row['letter'] === substr($row['dimension'], 1, 1) ? 'font-bold text-accent' : 'text-zinc-500' }}">{{ $row['right'] }} ({{ substr($row['dimension'], 1, 1) }})</span>
                </div>
                <div class="relative mt-1.5 h-2.5 w-full rounded-full bg-zinc-100 dark:bg-zinc-800">
                    <div class="absolute inset-y-0 left-1/2 w-px bg-zinc-300"></div>
                    <div class="absolute top-1/2 size-4 -translate-x-1/2 -translate-y-1/2 rounded-full bg-accent ring-2 ring-white" style="left: {{ $row['percent'] }}%"></div>
                </div>
                <p class="mt-1 text-center text-xs text-zinc-400">Skor {{ $row['sum'] }} dari 40 (batas tengah 24)</p>
            </div>
        @endforeach
    </div>

    <x-oejts-attribution />
</div>
