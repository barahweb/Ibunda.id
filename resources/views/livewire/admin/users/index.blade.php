<?php

use App\Models\User;
use App\Services\UserManagementService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;

    public string $search = '';

    public string $role = '';

    public function mount(): void
    {
        $this->authorize('viewAny', User::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedRole(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function users()
    {
        return User::query()
            ->when(
                $this->search,
                fn ($query) => $query->where(
                    fn ($nameOrEmail) => $nameOrEmail
                        ->where('name', 'like', "%{$this->search}%")
                        ->orWhere('email', 'like', "%{$this->search}%")
                )
            )
            ->when($this->role, fn ($query) => $query->where('role', $this->role))
            ->latest()
            ->paginate(15);
    }

    #[Computed]
    public function stats(): array
    {
        return [
            'total' => User::query()->count(),
            'admin' => User::query()->where('role', User::ROLE_ADMIN)->count(),
            'participant' => User::query()->where('role', User::ROLE_PARTICIPANT)->count(),
        ];
    }

    public function toggleRole(string $userId, UserManagementService $userManagementService): void
    {
        $target = User::findOrFail($userId);

        $this->authorize('updateRole', $target);

        try {
            $userManagementService->toggleRole($target);
        } catch (\DomainException $e) {
            Flux::toast(text: $e->getMessage(), variant: 'danger');
        }
    }
}; ?>

<div class="flex flex-col gap-6">
    <div>
        <flux:heading size="xl" class="font-display">Kelola User</flux:heading>
        <flux:subheading>Lihat semua user dan atur siapa yang jadi admin.</flux:subheading>
    </div>

    <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
        <div class="rounded-2xl border border-zinc-200 p-5 dark:border-zinc-700">
            <div class="flex size-10 items-center justify-center rounded-xl bg-blue-100 text-accent dark:bg-blue-950">
                <flux:icon.users class="size-5" />
            </div>
            <p class="mt-4 font-display text-2xl font-extrabold text-zinc-900 dark:text-white">{{ $this->stats['total'] }}</p>
            <p class="mt-0.5 text-sm font-semibold text-zinc-500 dark:text-zinc-400">Total User</p>
        </div>
        <div class="rounded-2xl border border-zinc-200 p-5 dark:border-zinc-700">
            <div class="flex size-10 items-center justify-center rounded-xl bg-amber-100 text-amber-600 dark:bg-amber-950">
                <flux:icon.shield-check class="size-5" />
            </div>
            <p class="mt-4 font-display text-2xl font-extrabold text-zinc-900 dark:text-white">{{ $this->stats['admin'] }}</p>
            <p class="mt-0.5 text-sm font-semibold text-zinc-500 dark:text-zinc-400">Admin</p>
        </div>
        <div class="rounded-2xl border border-zinc-200 p-5 dark:border-zinc-700">
            <div class="flex size-10 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600 dark:bg-emerald-950">
                <flux:icon.user class="size-5" />
            </div>
            <p class="mt-4 font-display text-2xl font-extrabold text-zinc-900 dark:text-white">{{ $this->stats['participant'] }}</p>
            <p class="mt-0.5 text-sm font-semibold text-zinc-500 dark:text-zinc-400">Peserta</p>
        </div>
    </div>

    <div class="flex flex-wrap gap-3">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Cari nama atau email…" class="max-w-sm" />

        <flux:select wire:model.live="role" placeholder="Semua role" class="max-w-xs">
            <flux:select.option value="">Semua role</flux:select.option>
            <flux:select.option value="admin">Admin</flux:select.option>
            <flux:select.option value="participant">Peserta</flux:select.option>
        </flux:select>
    </div>

    <div class="overflow-x-auto rounded-2xl border border-zinc-200 dark:border-zinc-700">
        <table class="w-full text-left text-sm">
            <thead class="bg-zinc-50 text-xs uppercase text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                <tr>
                    <th class="px-5 py-3.5">Nama</th>
                    <th class="px-5 py-3.5">Email</th>
                    <th class="px-5 py-3.5">Role</th>
                    <th class="px-5 py-3.5">Terdaftar</th>
                    <th class="px-5 py-3.5 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($this->users as $user)
                    <tr wire:key="user-{{ $user->id }}" class="transition hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                        <td class="px-5 py-4 font-semibold text-zinc-900 dark:text-white">
                            {{ $user->name }}
                            @if ($user->is(Auth::user()))
                                <span class="ml-1 text-xs font-normal text-zinc-400">(Kamu)</span>
                            @endif
                        </td>
                        <td class="px-5 py-4 text-zinc-500 dark:text-zinc-400">{{ $user->email }}</td>
                        <td class="px-5 py-4">
                            <flux:badge :color="$user->isAdmin() ? 'blue' : 'zinc'" size="sm">
                                {{ $user->isAdmin() ? 'Admin' : 'Peserta' }}
                            </flux:badge>
                        </td>
                        <td class="px-5 py-4 text-zinc-500 dark:text-zinc-400">{{ $user->created_at->format('d M Y') }}</td>
                        <td class="px-5 py-4">
                            <div class="flex justify-center">
                                @if ($user->is(Auth::user()))
                                    <span class="text-sm text-zinc-300">—</span>
                                @else
                                    <flux:button
                                        size="sm"
                                        variant="ghost"
                                        wire:click="toggleRole('{{ $user->id }}')"
                                        wire:confirm="{{ $user->isAdmin() ? 'Cabut role admin dari '.$user->name.'?' : 'Jadikan '.$user->name.' admin?' }}"
                                    >
                                        {{ $user->isAdmin() ? 'Cabut Admin' : 'Jadikan Admin' }}
                                    </flux:button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-5 py-14 text-center">
                            <flux:icon.users class="mx-auto size-8 text-zinc-300" />
                            <p class="mt-3 text-sm text-zinc-500 dark:text-zinc-400">
                                Gak ada user yang cocok sama filter ini.
                            </p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $this->users->links() }}
</div>
