<?php

use App\Models\Assessment;
use App\Models\AssessmentQuestion;
use App\Services\AssessmentQuestionService;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public Assessment $assessment;

    public bool $showFormModal = false;

    public ?string $editingQuestionId = null;

    public string $dimension = 'EI';

    public string $statementLeft = '';

    public string $statementRight = '';

    public function mount(Assessment $assessment): void
    {
        $this->authorize('update', $assessment);

        $this->assessment = $assessment;
    }

    #[Computed]
    public function questions()
    {
        return $this->assessment->questions()->get();
    }

    #[Computed]
    public function dimensionCounts(): array
    {
        $counts = $this->questions->countBy('dimension');

        return collect(AssessmentQuestion::DIMENSIONS)
            ->mapWithKeys(fn ($dimension) => [$dimension => $counts->get($dimension, 0)])
            ->all();
    }

    public function createQuestion(): void
    {
        $this->authorize('update', $this->assessment);

        $this->resetForm();
        $this->showFormModal = true;
    }

    public function editQuestion(string $questionId): void
    {
        $this->authorize('update', $this->assessment);

        $question = $this->assessment->questions()->findOrFail($questionId);

        $this->editingQuestionId = $question->id;
        $this->dimension = $question->dimension;
        $this->statementLeft = $question->statement_left;
        $this->statementRight = $question->statement_right;
        $this->showFormModal = true;
    }

    public function save(AssessmentQuestionService $assessmentQuestionService): void
    {
        $this->authorize('update', $this->assessment);

        $validated = $this->validate([
            'dimension' => ['required', Rule::in(AssessmentQuestion::DIMENSIONS)],
            'statementLeft' => ['required', 'string', 'max:255'],
            'statementRight' => ['required', 'string', 'max:255'],
        ]);

        $data = [
            'dimension' => $validated['dimension'],
            'statement_left' => $validated['statementLeft'],
            'statement_right' => $validated['statementRight'],
        ];

        if ($this->editingQuestionId) {
            $question = $this->assessment->questions()->findOrFail($this->editingQuestionId);
            $assessmentQuestionService->update($question, $data);
        } else {
            $assessmentQuestionService->create($this->assessment, $data);
        }

        unset($this->questions, $this->dimensionCounts);
        $this->showFormModal = false;
        $this->resetForm();
    }

    public function deleteQuestion(string $questionId, AssessmentQuestionService $assessmentQuestionService): void
    {
        $this->authorize('update', $this->assessment);

        $question = $this->assessment->questions()->findOrFail($questionId);

        try {
            $assessmentQuestionService->delete($question);
        } catch (\DomainException $e) {
            Flux::toast(text: $e->getMessage(), variant: 'danger');

            return;
        }

        unset($this->questions, $this->dimensionCounts);
    }

    public function moveUp(string $questionId, AssessmentQuestionService $assessmentQuestionService): void
    {
        $this->authorize('update', $this->assessment);

        $question = $this->assessment->questions()->findOrFail($questionId);
        $assessmentQuestionService->moveUp($question);
        unset($this->questions);
    }

    public function moveDown(string $questionId, AssessmentQuestionService $assessmentQuestionService): void
    {
        $this->authorize('update', $this->assessment);

        $question = $this->assessment->questions()->findOrFail($questionId);
        $assessmentQuestionService->moveDown($question);
        unset($this->questions);
    }

    private function resetForm(): void
    {
        $this->editingQuestionId = null;
        $this->dimension = 'EI';
        $this->statementLeft = '';
        $this->statementRight = '';
        $this->resetValidation();
    }
}; ?>

<div class="flex flex-col gap-6">
    <div>
        <flux:link :href="route('admin.assessments.index')" wire:navigate class="text-sm">&larr; Kembali ke Kelola Tes Kepribadian</flux:link>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" class="font-display">{{ $assessment->title }}</flux:heading>
            <flux:subheading>Kelola pernyataan buat assessment ini.</flux:subheading>
        </div>

        <flux:button variant="primary" icon="plus" wire:click="createQuestion" class="transition hover:-translate-y-0.5">
            Tambah Pernyataan
        </flux:button>
    </div>

    <div class="rounded-2xl border border-zinc-200 p-4 text-sm dark:border-zinc-700">
        <span class="font-semibold text-zinc-900 dark:text-white">{{ $this->questions->count() }}/32 pernyataan</span>
        <span class="text-zinc-400">&middot;</span>
        @foreach ($this->dimensionCounts as $dimension => $count)
            <span class="{{ $count === 8 ? 'text-zinc-500 dark:text-zinc-400' : 'font-semibold text-red-500' }}">{{ $dimension }} {{ $count }}/8</span>
            @if (!$loop->last)
                <span class="text-zinc-300 dark:text-zinc-600">,</span>
            @endif
        @endforeach
    </div>

    <div class="flex flex-col gap-3">
        @forelse ($this->questions as $index => $question)
            <div wire:key="question-{{ $question->id }}" class="rounded-2xl border border-zinc-200 p-5 transition hover:shadow-md dark:border-zinc-700">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex flex-1 gap-3">
                        <div class="flex size-7 shrink-0 items-center justify-center rounded-full bg-blue-100 font-display text-xs font-bold text-accent dark:bg-blue-950">
                            {{ $index + 1 }}
                        </div>
                        <div class="flex-1">
                            <flux:badge size="sm" color="zinc">{{ $question->dimension }}</flux:badge>
                            <p class="mt-2 text-sm text-zinc-900 dark:text-white">
                                <span class="font-semibold">{{ $question->statement_left }}</span>
                                <span class="text-zinc-400">&harr;</span>
                                <span class="font-semibold">{{ $question->statement_right }}</span>
                            </p>
                        </div>
                    </div>

                    <div class="flex shrink-0 flex-col items-end gap-1">
                        <div class="flex gap-1">
                            <flux:button size="sm" variant="ghost" icon="chevron-up" wire:click="moveUp('{{ $question->id }}')" :disabled="$index === 0" />
                            <flux:button size="sm" variant="ghost" icon="chevron-down" wire:click="moveDown('{{ $question->id }}')" :disabled="$index === $this->questions->count() - 1" />
                        </div>
                        <div class="flex gap-1">
                            <flux:button size="sm" variant="ghost" icon="pencil" wire:click="editQuestion('{{ $question->id }}')" />
                            <flux:button
                                size="sm"
                                variant="ghost"
                                icon="trash"
                                wire:click="deleteQuestion('{{ $question->id }}')"
                                wire:confirm="Yakin mau hapus pernyataan ini?"
                            />
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="rounded-2xl border border-dashed border-zinc-300 p-10 text-center dark:border-zinc-700">
                <flux:icon.face-smile class="mx-auto size-8 text-zinc-300" />
                <p class="mt-3 text-sm text-zinc-500 dark:text-zinc-400">
                    Belum ada pernyataan. Klik "Tambah Pernyataan" buat bikin yang pertama.
                </p>
            </div>
        @endforelse
    </div>

    <flux:modal wire:model.self="showFormModal" class="max-w-lg">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg" class="font-display">{{ $editingQuestionId ? 'Edit Pernyataan' : 'Tambah Pernyataan' }}</flux:heading>
                <flux:subheading>Peserta milih skala 1-5 di antara dua pernyataan ini.</flux:subheading>
            </div>

            <flux:select wire:model="dimension" label="Dimensi">
                <flux:select.option value="EI">Extraversion (E) &ndash; Introversion (I)</flux:select.option>
                <flux:select.option value="SN">Sensing (S) &ndash; Intuition (N)</flux:select.option>
                <flux:select.option value="TF">Thinking (T) &ndash; Feeling (F)</flux:select.option>
                <flux:select.option value="JP">Judging (J) &ndash; Perceiving (P)</flux:select.option>
            </flux:select>

            <flux:input wire:model="statementLeft" label="Pernyataan kiri (skala 1)" placeholder="Contoh: Suka bikin daftar" />

            <flux:input wire:model="statementRight" label="Pernyataan kanan (skala 5)" placeholder="Contoh: Mengandalkan ingatan" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">Batal</flux:button>
                </flux:modal.close>

                <flux:button variant="primary" type="submit">Simpan</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
