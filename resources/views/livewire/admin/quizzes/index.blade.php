<?php

use App\Models\Quiz;
use App\Services\QuizService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;

    public string $search = '';

    public bool $showFormModal = false;

    public ?string $editingQuizId = null;

    public string $title = '';

    public string $description = '';

    public ?int $time_limit_minutes = null;

    public ?int $passing_score = null;

    public function mount(): void
    {
        $this->authorize('viewAny', Quiz::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function quizzes()
    {
        return Quiz::query()
            ->with('creator')
            ->when($this->search, fn ($query) => $query->where('title', 'like', "%{$this->search}%"))
            ->latest()
            ->paginate(10);
    }

    public function createQuiz(): void
    {
        $this->authorize('create', Quiz::class);

        $this->resetForm();
        $this->showFormModal = true;
    }

    public function editQuiz(string $quizId): void
    {
        $quiz = Quiz::findOrFail($quizId);

        $this->authorize('update', $quiz);

        $this->editingQuizId = $quiz->id;
        $this->title = $quiz->title;
        $this->description = (string) $quiz->description;
        $this->time_limit_minutes = $quiz->time_limit_minutes;
        $this->passing_score = $quiz->passing_score;
        $this->showFormModal = true;
    }

    public function save(QuizService $quizService): void
    {
        $validated = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'time_limit_minutes' => ['nullable', 'integer', 'min:1', 'max:600'],
            'passing_score' => ['nullable', 'integer', 'between:0,100'],
        ]);

        if ($this->editingQuizId) {
            $quiz = Quiz::findOrFail($this->editingQuizId);
            $this->authorize('update', $quiz);
            $quizService->update($quiz, $validated);
        } else {
            $this->authorize('create', Quiz::class);
            $quizService->create(Auth::user(), $validated);
        }

        $this->showFormModal = false;
        $this->resetForm();
    }

    public function togglePublish(string $quizId, QuizService $quizService): void
    {
        $quiz = Quiz::findOrFail($quizId);

        $this->authorize('update', $quiz);

        $quizService->togglePublish($quiz);
    }

    public function deleteQuiz(string $quizId, QuizService $quizService): void
    {
        $quiz = Quiz::findOrFail($quizId);

        $this->authorize('delete', $quiz);

        $quizService->delete($quiz);
        $this->resetPage();
    }

    private function resetForm(): void
    {
        $this->editingQuizId = null;
        $this->title = '';
        $this->description = '';
        $this->time_limit_minutes = null;
        $this->passing_score = null;
        $this->resetValidation();
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">Quiz Management</flux:heading>
            <flux:subheading>Kelola quiz yang bisa dikerjakan peserta.</flux:subheading>
        </div>

        <flux:button variant="primary" icon="plus" wire:click="createQuiz">
            Tambah Quiz
        </flux:button>
    </div>

    <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Cari judul quiz…" class="max-w-sm" />

    <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
        <table class="w-full text-left text-sm">
            <thead class="bg-zinc-50 text-xs uppercase text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                <tr>
                    <th class="px-4 py-3">Judul</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Waktu</th>
                    <th class="px-4 py-3">Nilai Lulus</th>
                    <th class="px-4 py-3">Dibuat oleh</th>
                    <th class="px-4 py-3 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($this->quizzes as $quiz)
                    <tr wire:key="quiz-{{ $quiz->id }}">
                        <td class="px-4 py-3 font-medium text-zinc-900 dark:text-white">{{ $quiz->title }}</td>
                        <td class="px-4 py-3">
                            <flux:badge :color="$quiz->isPublished() ? 'green' : 'zinc'" size="sm">
                                {{ $quiz->isPublished() ? 'Published' : 'Draft' }}
                            </flux:badge>
                        </td>
                        <td class="px-4 py-3 text-zinc-500 dark:text-zinc-400">
                            {{ $quiz->time_limit_minutes ? "{$quiz->time_limit_minutes} menit" : '—' }}
                        </td>
                        <td class="px-4 py-3 text-zinc-500 dark:text-zinc-400">
                            {{ $quiz->passing_score !== null ? "{$quiz->passing_score}%" : '—' }}
                        </td>
                        <td class="px-4 py-3 text-zinc-500 dark:text-zinc-400">{{ $quiz->creator->name }}</td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-1">
                                <flux:button size="sm" variant="ghost" :href="route('admin.quizzes.questions', $quiz)" wire:navigate>
                                    Soal
                                </flux:button>
                                <flux:button size="sm" variant="ghost" wire:click="togglePublish('{{ $quiz->id }}')">
                                    {{ $quiz->isPublished() ? 'Jadikan Draft' : 'Publish' }}
                                </flux:button>
                                <flux:button size="sm" variant="ghost" icon="pencil" wire:click="editQuiz('{{ $quiz->id }}')" />
                                <flux:button
                                    size="sm"
                                    variant="ghost"
                                    icon="trash"
                                    wire:click="deleteQuiz('{{ $quiz->id }}')"
                                    wire:confirm="Yakin mau hapus quiz ini? Semua soal di dalamnya ikut terhapus."
                                />
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-zinc-500 dark:text-zinc-400">
                            Belum ada quiz. Klik "Tambah Quiz" buat bikin yang pertama.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $this->quizzes->links() }}

    <flux:modal wire:model.self="showFormModal" class="max-w-lg">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $editingQuizId ? 'Edit Quiz' : 'Tambah Quiz' }}</flux:heading>
                <flux:subheading>Quiz baru tersimpan sebagai draft, publish lewat tombol di daftar.</flux:subheading>
            </div>

            <flux:input wire:model="title" label="Judul" placeholder="Contoh: Tes Wawasan Umum" />

            <flux:textarea wire:model="description" label="Deskripsi" placeholder="Opsional" rows="3" />

            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model="time_limit_minutes" type="number" label="Batas waktu (menit)" placeholder="Opsional" />
                <flux:input wire:model="passing_score" type="number" label="Nilai lulus (%)" placeholder="Opsional" />
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
