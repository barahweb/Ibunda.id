<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head', ['title' => config('app.name')])
    </head>
    <body class="min-h-screen bg-blue-50 text-zinc-900 antialiased">
        <div class="flex min-h-svh items-center justify-center p-6">
            <div class="flex w-full max-w-4xl flex-col overflow-hidden rounded-3xl bg-white shadow-2xl lg:flex-row">

                <div class="relative hidden shrink-0 flex-col justify-between overflow-hidden bg-linear-to-br from-accent to-blue-900 p-10 lg:flex lg:w-[380px]">
                    <div class="pointer-events-none absolute -right-12 -top-12 size-56 animate-blob-drift rounded-full bg-white opacity-10 blur-3xl"></div>
                    <div class="pointer-events-none absolute -bottom-14 -left-8 size-44 animate-blob-drift rounded-full bg-blue-400 opacity-25 blur-3xl [animation-delay:-2s]"></div>

                    <a href="{{ route('home') }}" class="relative z-10 flex items-center gap-2.5" wire:navigate>
                        <x-app-logo-icon class="size-8 text-white" />
                        <span class="font-display text-sm font-bold text-white">{{ config('app.name') }}</span>
                    </a>

                    <div class="relative z-10">
                        <div x-data="{ selected: null }" class="animate-gentle-bounce w-56 rounded-2xl bg-white p-4 shadow-2xl">
                            <div class="flex items-center gap-1.5">
                                <span class="size-1.5 rounded-full bg-emerald-500"></span>
                                <span class="text-[11px] font-semibold text-zinc-400">Soal 5 dari 8</span>
                            </div>
                            <p class="mt-2.5 text-sm font-bold text-zinc-900">7 + 8 = ?</p>
                            <div class="mt-2.5 flex gap-1.5 text-xs">
                                <div
                                    @click="selected = (selected === 14 ? null : 14)"
                                    class="flex-1 cursor-pointer rounded-lg border py-1.5 text-center transition"
                                    :class="selected === 14 ? 'border-accent bg-blue-50 font-bold text-blue-900' : 'border-zinc-200 text-zinc-600 hover:border-accent hover:bg-blue-50 hover:font-bold hover:text-blue-900'"
                                >14</div>
                                <div
                                    @click="selected = (selected === 15 ? null : 15)"
                                    class="flex-1 cursor-pointer rounded-lg border py-1.5 text-center transition"
                                    :class="selected === 15 ? 'border-accent bg-blue-50 font-bold text-blue-900' : 'border-zinc-200 text-zinc-600 hover:border-accent hover:bg-blue-50 hover:font-bold hover:text-blue-900'"
                                >15</div>
                                <div
                                    @click="selected = (selected === 16 ? null : 16)"
                                    class="flex-1 cursor-pointer rounded-lg border py-1.5 text-center transition"
                                    :class="selected === 16 ? 'border-accent bg-blue-50 font-bold text-blue-900' : 'border-zinc-200 text-zinc-600 hover:border-accent hover:bg-blue-50 hover:font-bold hover:text-blue-900'"
                                >16</div>
                            </div>
                        </div>
                        <h2 class="mt-7 font-display text-xl font-extrabold leading-snug text-white">
                            Kelola &amp; Kerjakan Quiz dengan Mudah
                        </h2>
                    </div>
                </div>

                <div class="flex flex-1 flex-col justify-center p-8 sm:p-12">
                    <a href="{{ route('home') }}" class="mb-6 flex items-center gap-2 lg:hidden" wire:navigate>
                        <x-app-logo-icon class="size-7 text-accent" />
                        <span class="font-display text-sm font-bold text-zinc-900">{{ config('app.name') }}</span>
                    </a>

                    <div class="flex flex-col gap-6">
                        {{ $slot }}
                    </div>
                </div>

            </div>
        </div>
        @fluxScripts
    </body>
</html>
