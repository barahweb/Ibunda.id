<a
    {{ $attributes->merge(['class' => 'text-sm font-semibold text-accent underline decoration-accent/40 underline-offset-2 transition duration-200 ease-out hover:decoration-accent']) }}
    wire:navigate
>
    {{ $slot }}
</a>
