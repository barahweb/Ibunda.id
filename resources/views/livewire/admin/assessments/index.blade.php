<?php

use App\Models\Assessment;
use App\Services\AssessmentService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;

    public string $search = '';

    public bool $showFormModal = false;

    public ?string $editingAssessmentId = null;

    public string $title = '';

    public string $description = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Assessment::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function assessments()
    {
        return Assessment::query()
            ->with('creator')
            ->withCount('questions')
            ->when($this->search, fn ($query) => $query->where('title', 'like', "%{$this->search}%"))
            ->latest()
            ->paginate(10);
    }

    public function createAssessment(): void
    {
        $this->authorize('create', Assessment::class);

        $this->resetForm();
        $this->showFormModal = true;
    }

    public function editAssessment(string $assessmentId): void
    {
        $assessment = Assessment::findOrFail($assessmentId);

        $this->authorize('update', $assessment);

        $this->editingAssessmentId = $assessment->id;
        $this->title = $assessment->title;
        $this->description = (string) $assessment->description;
        $this->showFormModal = true;
    }

    public function save(AssessmentService $assessmentService): void
    {
        $validated = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
        ]);

        if ($this->editingAssessmentId) {
            $assessment = Assessment::findOrFail($this->editingAssessmentId);
            $this->authorize('update', $assessment);
            $assessmentService->update($assessment, $validated);
        } else {
            $this->authorize('create', Assessment::class);
            $assessmentService->create(Auth::user(), $validated);
        }

        $this->showFormModal = false;
        $this->resetForm();
    }

    public function togglePublish(string $assessmentId, AssessmentService $assessmentService): void
    {
        $assessment = Assessment::findOrFail($assessmentId);

        $this->authorize('update', $assessment);

        try {
            $assessmentService->togglePublish($assessment);
        } catch (\DomainException $e) {
            Flux::toast(text: $e->getMessage(), variant: 'danger');
        }
    }

    public function deleteAssessment(string $assessmentId, AssessmentService $assessmentService): void
    {
        $assessment = Assessment::findOrFail($assessmentId);

        $this->authorize('delete', $assessment);

        $assessmentService->delete($assessment);
        $this->resetPage();
    }

    private function resetForm(): void
    {
        $this->editingAssessmentId = null;
        $this->title = '';
        $this->description = '';
        $this->resetValidation();
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" class="font-display">Kelola Tes Kepribadian</flux:heading>
            <flux:subheading>Kelola assessment kepribadian yang bisa dikerjakan peserta.</flux:subheading>
        </div>

        <flux:button variant="primary" icon="plus" wire:click="createAssessment" class="transition hover:-translate-y-0.5">
            Tambah Assessment
        </flux:button>
    </div>

    <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Cari judul assessment…" class="max-w-sm" />

    <div class="overflow-x-auto rounded-2xl border border-zinc-200 dark:border-zinc-700">
        <table class="w-full text-left text-sm">
            <thead class="bg-zinc-50 text-xs uppercase text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                <tr>
                    <th class="px-5 py-3.5">Judul</th>
                    <th class="px-5 py-3.5">Status</th>
                    <th class="px-5 py-3.5">Pernyataan</th>
                    <th class="px-5 py-3.5">Dibuat oleh</th>
                    <th class="px-5 py-3.5 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($this->assessments as $assessment)
                    <tr wire:key="assessment-{{ $assessment->id }}" class="transition hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                        <td class="px-5 py-4 font-semibold text-zinc-900 dark:text-white">{{ $assessment->title }}</td>
                        <td class="px-5 py-4">
                            <flux:badge :color="$assessment->isPublished() ? 'green' : 'zinc'" size="sm">
                                {{ $assessment->isPublished() ? 'Published' : 'Draft' }}
                            </flux:badge>
                        </td>
                        <td class="px-5 py-4">
                            @if ($assessment->questions_count === 32)
                                <span class="text-zinc-500 dark:text-zinc-400">{{ $assessment->questions_count }}/32 pernyataan</span>
                            @else
                                <span class="font-semibold text-red-500">{{ $assessment->questions_count }}/32 pernyataan &middot; belum bisa publish</span>
                            @endif
                        </td>
                        <td class="px-5 py-4 text-zinc-500 dark:text-zinc-400">{{ $assessment->creator->name }}</td>
                        <td class="px-5 py-4">
                            <div class="flex justify-center gap-1">
                                <flux:button size="sm" variant="ghost" :href="route('admin.assessments.questions', $assessment)" wire:navigate>
                                    Pernyataan
                                </flux:button>
                                <flux:button size="sm" variant="ghost" :href="route('admin.assessments.submissions', $assessment)" wire:navigate>
                                    Hasil
                                </flux:button>
                                <flux:button size="sm" variant="ghost" wire:click="togglePublish('{{ $assessment->id }}')">
                                    {{ $assessment->isPublished() ? 'Jadikan Draft' : 'Publish' }}
                                </flux:button>
                                <flux:button size="sm" variant="ghost" icon="pencil" wire:click="editAssessment('{{ $assessment->id }}')" />
                                <flux:button
                                    size="sm"
                                    variant="ghost"
                                    icon="trash"
                                    wire:click="deleteAssessment('{{ $assessment->id }}')"
                                    wire:confirm="Yakin mau hapus assessment ini? Semua pernyataan di dalamnya ikut terhapus."
                                />
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-5 py-14 text-center">
                            <flux:icon.face-smile class="mx-auto size-8 text-zinc-300" />
                            <p class="mt-3 text-sm text-zinc-500 dark:text-zinc-400">
                                Belum ada assessment. Klik "Tambah Assessment" buat bikin yang pertama.
                            </p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $this->assessments->links() }}

    <flux:modal wire:model.self="showFormModal" class="max-w-lg">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg" class="font-display">{{ $editingAssessmentId ? 'Edit Assessment' : 'Tambah Assessment' }}</flux:heading>
                <flux:subheading>Assessment baru tersimpan sebagai draft, publish lewat tombol di daftar setelah 32 pernyataannya lengkap.</flux:subheading>
            </div>

            <flux:input wire:model="title" label="Judul" placeholder="Contoh: Tes Kepribadian (OEJTS)" />

            <flux:textarea wire:model="description" label="Deskripsi" placeholder="Opsional" rows="3" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">Batal</flux:button>
                </flux:modal.close>

                <flux:button variant="primary" type="submit">Simpan</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
