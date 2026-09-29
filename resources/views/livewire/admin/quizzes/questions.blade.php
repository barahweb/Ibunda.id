<?php

use App\Models\Question;
use App\Models\Quiz;
use App\Services\QuestionService;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public Quiz $quiz;

    public bool $showFormModal = false;

    public ?string $editingQuestionId = null;

    public string $questionText = '';

    public int $points = 1;

    /** @var array<int, string> */
    public array $options = [];

    public int $correctIndex = 0;

    public function mount(Quiz $quiz): void
    {
        $this->authorize('update', $quiz);

        $this->quiz = $quiz;
    }

    #[Computed]
    public function questions()
    {
        return $this->quiz->questions()->with('options')->get();
    }

    public function createQuestion(): void
    {
        $this->authorize('update', $this->quiz);

        $this->resetForm();
        $this->showFormModal = true;
    }

    public function editQuestion(string $questionId): void
    {
        $this->authorize('update', $this->quiz);

        $question = $this->quiz->questions()->with('options')->findOrFail($questionId);

        $this->editingQuestionId = $question->id;
        $this->questionText = $question->question_text;
        $this->points = $question->points;
        $this->options = $question->options->pluck('option_text')->all();
        $this->correctIndex = $question->options->search(fn ($option) => $option->is_correct) ?: 0;
        $this->showFormModal = true;
    }

    public function addOption(): void
    {
        $this->options[] = '';
    }

    public function removeOption(int $index): void
    {
        if (count($this->options) <= 2) {
            return;
        }

        unset($this->options[$index]);
        $this->options = array_values($this->options);

        if ($this->correctIndex === $index) {
            $this->correctIndex = 0;
        } elseif ($this->correctIndex > $index) {
            $this->correctIndex--;
        }
    }

    public function save(QuestionService $questionService): void
    {
        $this->authorize('update', $this->quiz);

        $validated = $this->validate([
            'questionText' => ['required', 'string', 'max:1000'],
            'points' => ['required', 'integer', 'min:1', 'max:100'],
            'options' => ['required', 'array', 'min:2'],
            'options.*' => ['required', 'string', 'max:255'],
            'correctIndex' => ['required', 'integer', Rule::in(array_keys($this->options))],
        ]);

        $data = [
            'question_text' => $validated['questionText'],
            'points' => $validated['points'],
            'options' => $validated['options'],
            'correct_index' => $validated['correctIndex'],
        ];

        if ($this->editingQuestionId) {
            $question = $this->quiz->questions()->findOrFail($this->editingQuestionId);
            $questionService->update($question, $data);
        } else {
            $questionService->create($this->quiz, $data);
        }

        unset($this->questions);
        $this->showFormModal = false;
        $this->resetForm();
    }

    public function deleteQuestion(string $questionId, QuestionService $questionService): void
    {
        $this->authorize('update', $this->quiz);

        $question = $this->quiz->questions()->findOrFail($questionId);
        $questionService->delete($question);
        unset($this->questions);
    }

    public function moveUp(string $questionId, QuestionService $questionService): void
    {
        $this->authorize('update', $this->quiz);

        $question = $this->quiz->questions()->findOrFail($questionId);
        $questionService->moveUp($question);
        unset($this->questions);
    }

    public function moveDown(string $questionId, QuestionService $questionService): void
    {
        $this->authorize('update', $this->quiz);

        $question = $this->quiz->questions()->findOrFail($questionId);
        $questionService->moveDown($question);
        unset($this->questions);
    }

    private function resetForm(): void
    {
        $this->editingQuestionId = null;
        $this->questionText = '';
        $this->points = 1;
        $this->options = ['', '', '', ''];
        $this->correctIndex = 0;
        $this->resetValidation();
    }
}; ?>

<div class="flex flex-col gap-6">
    <div>
        <flux:link :href="route('admin.quizzes.index')" wire:navigate class="text-sm">&larr; Kembali ke Quiz Management</flux:link>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ $quiz->title }}</flux:heading>
            <flux:subheading>Kelola soal &amp; pilihan jawaban buat quiz ini.</flux:subheading>
        </div>

        <flux:button variant="primary" icon="plus" wire:click="createQuestion">
            Tambah Soal
        </flux:button>
    </div>

    <div class="flex flex-col gap-3">
        @forelse ($this->questions as $index => $question)
            <div wire:key="question-{{ $question->id }}" class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex-1">
                        <p class="font-medium text-zinc-900 dark:text-white">{{ $index + 1 }}. {{ $question->question_text }}</p>
                        <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ $question->points }} poin</p>

                        <ul class="mt-3 space-y-1">
                            @foreach ($question->options as $option)
                                <li class="flex items-center gap-2 text-sm {{ $option->is_correct ? 'font-medium text-green-600 dark:text-green-400' : 'text-zinc-600 dark:text-zinc-400' }}">
                                    <flux:icon.check-circle :variant="$option->is_correct ? 'solid' : 'outline'" class="size-4 shrink-0" />
                                    {{ $option->option_text }}
                                </li>
                            @endforeach
                        </ul>
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
                                wire:confirm="Yakin mau hapus soal ini?"
                            />
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="rounded-lg border border-dashed border-zinc-300 p-8 text-center text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                Belum ada soal. Klik "Tambah Soal" buat bikin yang pertama.
            </div>
        @endforelse
    </div>

    <flux:modal wire:model.self="showFormModal" class="max-w-2xl">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $editingQuestionId ? 'Edit Soal' : 'Tambah Soal' }}</flux:heading>
                <flux:subheading>Tandai satu opsi sebagai jawaban benar.</flux:subheading>
            </div>

            <flux:textarea wire:model="questionText" label="Pertanyaan" rows="3" />

            <flux:input wire:model="points" type="number" label="Poin" class="max-w-[140px]" />

            <div class="space-y-3">
                <flux:label>Pilihan Jawaban</flux:label>

                @foreach ($options as $i => $option)
                    <div class="flex items-center gap-2" wire:key="option-{{ $i }}">
                        <input type="radio" wire:model="correctIndex" value="{{ $i }}" class="size-4 text-green-600" />
                        <flux:input wire:model="options.{{ $i }}" placeholder="Opsi {{ $i + 1 }}" class="flex-1" />
                        <flux:button
                            size="sm"
                            variant="ghost"
                            icon="x-mark"
                            type="button"
                            wire:click="removeOption({{ $i }})"
                            :disabled="count($options) <= 2"
                        />
                    </div>
                @endforeach

                @error('options')
                    <flux:error>{{ $message }}</flux:error>
                @enderror
                @error('correctIndex')
                    <flux:error>Pilih salah satu opsi sebagai jawaban benar.</flux:error>
                @enderror

                <flux:button size="sm" variant="filled" type="button" icon="plus" wire:click="addOption">
                    Tambah Opsi
                </flux:button>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">Batal</flux:button>
                </flux:modal.close>

                <flux:button variant="primary" type="submit">Simpan</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
