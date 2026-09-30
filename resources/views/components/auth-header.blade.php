@props([
    'title',
    'description',
])

<div class="flex w-full flex-col gap-2">
    <h1 class="font-display text-2xl font-extrabold text-zinc-900">{{ $title }}</h1>
    <p class="text-sm text-zinc-500">{{ $description }}</p>
</div>
