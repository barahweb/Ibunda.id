<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head', ['title' => config('app.name')])
    </head>
    <body class="bg-white text-zinc-900 antialiased">
        <header class="sticky top-0 z-50 border-b border-zinc-200 bg-white/80 backdrop-blur-md">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
                <a href="{{ route('home') }}" class="flex items-center gap-2 font-display font-bold text-zinc-900" wire:navigate>
                    <x-app-logo-icon class="size-8 text-accent" />
                    {{ config('app.name') }}
                </a>

                <nav class="flex items-center gap-6">
                    <div class="hidden items-center gap-6 text-sm font-semibold text-zinc-600 sm:flex">
                        <a href="#fitur" class="transition hover:text-accent">Fitur</a>
                        <a href="#cara-kerja" class="transition hover:text-accent">Cara Kerja</a>
                    </div>
                    <div class="flex items-center gap-2">
                        @auth
                            <flux:button variant="primary" :href="route('dashboard')" wire:navigate>Dashboard</flux:button>
                        @else
                            <flux:button variant="ghost" :href="route('login')" wire:navigate>Masuk</flux:button>
                            <flux:button variant="primary" :href="route('register')" wire:navigate>Daftar</flux:button>
                        @endauth
                    </div>
                </nav>
            </div>
        </header>

        <section class="relative overflow-hidden bg-linear-to-br from-accent to-blue-900 px-6 py-20">
            <div class="pointer-events-none absolute -left-10 -top-16 size-80 animate-blob-drift rounded-full bg-white opacity-10 blur-3xl"></div>
            <div class="pointer-events-none absolute -right-10 bottom-0 size-64 animate-blob-drift rounded-full bg-blue-400 opacity-25 blur-3xl [animation-delay:-3s]"></div>

            <div class="relative mx-auto flex max-w-6xl flex-col items-center gap-12 lg:flex-row lg:justify-between">
                <div class="max-w-lg text-center lg:text-left">
                    <span class="inline-flex rounded-full bg-white/15 px-4 py-1.5 text-xs font-bold uppercase tracking-wide text-blue-100">
                        Untuk Sekolah &amp; Perusahaan
                    </span>
                    <h1 class="mt-5 font-display text-4xl font-extrabold leading-tight text-white sm:text-5xl">
                        Bikin &amp; Kerjakan Quiz, Tanpa Ribet
                    </h1>
                    <p class="mt-4 text-base leading-relaxed text-blue-100">
                        Admin susun soal dalam hitungan menit, peserta kerjakan kapan saja, nilai keluar otomatis begitu submit.
                    </p>
                    <div class="mt-8 flex flex-wrap items-center justify-center gap-3 lg:justify-start">
                        @auth
                            <flux:button :href="route('quizzes.index')" wire:navigate class="!bg-white !text-accent shadow-lg transition hover:!bg-blue-50 hover:-translate-y-0.5">
                                Kerjakan Quiz
                            </flux:button>
                        @else
                            <flux:button :href="route('register')" wire:navigate class="!bg-white !text-accent shadow-lg transition hover:!bg-blue-50 hover:-translate-y-0.5">
                                Coba Gratis
                            </flux:button>
                            <flux:button variant="ghost" href="#fitur" class="!text-white !border !border-white/40 transition hover:!bg-white/10 hover:-translate-y-0.5">
                                Lihat Fitur
                            </flux:button>
                        @endauth
                    </div>
                </div>

                <div class="relative w-full max-w-sm">
                    <div class="animate-gentle-bounce absolute -right-4 -top-4 z-10 flex items-center gap-1.5 rounded-full bg-white px-3.5 py-2 shadow-xl">
                        <flux:icon.star class="size-3.5 text-amber-500" variant="solid" />
                        <span class="text-xs font-bold text-zinc-900">+10 poin</span>
                    </div>
                    <div x-data="{ selected: null }" class="-rotate-3 rounded-2xl bg-white p-6 shadow-2xl">
                        <div class="flex items-center gap-2">
                            <span class="size-2 rounded-full bg-emerald-500"></span>
                            <span class="text-xs font-semibold text-zinc-400">Soal 3 dari 10</span>
                        </div>
                        <p class="mt-4 text-base font-bold text-zinc-900">Ibukota Indonesia adalah?</p>
                        <div class="mt-4 flex flex-col gap-2.5">
                            <div
                                @click="selected = (selected === 'surabaya' ? null : 'surabaya')"
                                class="group flex cursor-pointer items-center justify-between rounded-lg border px-3.5 py-2.5 text-sm transition"
                                :class="selected === 'surabaya' ? 'border-accent bg-blue-50 font-bold text-blue-900' : 'border-zinc-200 text-zinc-600 hover:border-accent hover:bg-blue-50 hover:font-bold hover:text-blue-900'"
                            >
                                Surabaya
                                <span :class="selected === 'surabaya' ? 'opacity-100' : 'opacity-0 group-hover:opacity-100'" class="transition">
                                    <flux:icon.check-circle class="size-4 text-accent" />
                                </span>
                            </div>
                            <div
                                @click="selected = (selected === 'jakarta' ? null : 'jakarta')"
                                class="group flex cursor-pointer items-center justify-between rounded-lg border px-3.5 py-2.5 text-sm transition"
                                :class="selected === 'jakarta' ? 'border-accent bg-blue-50 font-bold text-blue-900' : 'border-zinc-200 text-zinc-600 hover:border-accent hover:bg-blue-50 hover:font-bold hover:text-blue-900'"
                            >
                                Jakarta
                                <span :class="selected === 'jakarta' ? 'opacity-100' : 'opacity-0 group-hover:opacity-100'" class="transition">
                                    <flux:icon.check-circle class="size-4 text-accent" />
                                </span>
                            </div>
                            <div
                                @click="selected = (selected === 'bandung' ? null : 'bandung')"
                                class="group flex cursor-pointer items-center justify-between rounded-lg border px-3.5 py-2.5 text-sm transition"
                                :class="selected === 'bandung' ? 'border-accent bg-blue-50 font-bold text-blue-900' : 'border-zinc-200 text-zinc-600 hover:border-accent hover:bg-blue-50 hover:font-bold hover:text-blue-900'"
                            >
                                Bandung
                                <span :class="selected === 'bandung' ? 'opacity-100' : 'opacity-0 group-hover:opacity-100'" class="transition">
                                    <flux:icon.check-circle class="size-4 text-accent" />
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="border-b border-zinc-200 bg-blue-50 px-6 py-8">
            <div class="mx-auto grid max-w-6xl grid-cols-1 gap-8 text-center sm:grid-cols-3">
                <div>
                    <p class="font-display text-3xl font-extrabold text-accent">{{ $availableQuizCount }}</p>
                    <p class="mt-1 text-sm font-semibold text-zinc-600">Quiz Tersedia</p>
                </div>
                <div>
                    <p class="font-display text-3xl font-extrabold text-accent">{{ $completedAttemptCount }}</p>
                    <p class="mt-1 text-sm font-semibold text-zinc-600">Quiz Sudah Dikerjakan</p>
                </div>
                <div>
                    <p class="font-display text-3xl font-extrabold text-accent">{{ $averageScore !== null ? round($averageScore).'%' : '—' }}</p>
                    <p class="mt-1 text-sm font-semibold text-zinc-600">Rata-rata Skor</p>
                </div>
            </div>
        </section>

        <section id="fitur" class="px-6 py-20">
            <div class="mx-auto max-w-6xl">
                <div class="mx-auto max-w-xl text-center">
                    <h2 class="font-display text-3xl font-extrabold text-zinc-900">Semua yang Kamu Butuh, dalam Satu Tempat</h2>
                    <p class="mt-3 text-base text-zinc-500">Dari bikin soal sampai lihat hasil, semua langsung tersedia tanpa perlu tools tambahan.</p>
                </div>

                <div class="mt-12 grid grid-cols-1 gap-6 sm:grid-cols-3">
                    <div class="group rounded-2xl border border-zinc-200 p-7 transition duration-200 hover:-translate-y-1.5 hover:shadow-xl">
                        <div class="flex size-11 items-center justify-center rounded-xl bg-blue-100 text-accent transition duration-200 group-hover:scale-110 group-hover:rotate-6">
                            <flux:icon.pencil-square class="size-5" />
                        </div>
                        <h3 class="mt-5 font-display text-lg font-bold text-zinc-900">Kelola Quiz Mudah</h3>
                        <p class="mt-2 text-sm leading-relaxed text-zinc-500">Admin bikin quiz, tambah soal pilihan ganda, publish kapan siap.</p>
                    </div>
                    <div class="group rounded-2xl border border-zinc-200 p-7 transition duration-200 hover:-translate-y-1.5 hover:shadow-xl">
                        <div class="flex size-11 items-center justify-center rounded-xl bg-blue-100 text-accent transition duration-200 group-hover:scale-110 group-hover:rotate-6">
                            <flux:icon.clipboard-document-list class="size-5" />
                        </div>
                        <h3 class="mt-5 font-display text-lg font-bold text-zinc-900">Kerjakan Kapan Saja</h3>
                        <p class="mt-2 text-sm leading-relaxed text-zinc-500">Peserta pilih quiz, jawab soal, submit — semua dari satu halaman.</p>
                    </div>
                    <div class="group rounded-2xl border border-zinc-200 p-7 transition duration-200 hover:-translate-y-1.5 hover:shadow-xl">
                        <div class="flex size-11 items-center justify-center rounded-xl bg-blue-100 text-accent transition duration-200 group-hover:scale-110 group-hover:rotate-6">
                            <flux:icon.chart-bar class="size-5" />
                        </div>
                        <h3 class="mt-5 font-display text-lg font-bold text-zinc-900">Hasil Instan &amp; Detail</h3>
                        <p class="mt-2 text-sm leading-relaxed text-zinc-500">Skor otomatis dihitung, lengkap dengan breakdown per soal.</p>
                    </div>
                </div>
            </div>
        </section>

        <section id="cara-kerja" class="bg-zinc-50 px-6 py-20">
            <div class="mx-auto max-w-4xl text-center">
                <h2 class="font-display text-3xl font-extrabold text-zinc-900">Cara Kerjanya</h2>
                <p class="mt-3 text-base text-zinc-500">Tiga langkah, selesai.</p>

                <div class="relative mt-14 grid grid-cols-1 gap-10 sm:grid-cols-3">
                    <div class="absolute inset-x-20 top-[22px] hidden border-t-2 border-dashed border-zinc-300 sm:block"></div>

                    <div class="relative">
                        <div class="mx-auto flex size-11 items-center justify-center rounded-full bg-accent font-display text-lg font-bold text-white">1</div>
                        <h3 class="mt-4 font-display text-base font-bold text-zinc-900">Admin Buat Quiz</h3>
                        <p class="mx-auto mt-1.5 max-w-[240px] text-sm text-zinc-500">Susun soal &amp; pilihan jawaban dalam beberapa menit.</p>
                    </div>
                    <div class="relative">
                        <div class="mx-auto flex size-11 items-center justify-center rounded-full bg-accent font-display text-lg font-bold text-white">2</div>
                        <h3 class="mt-4 font-display text-base font-bold text-zinc-900">Peserta Kerjakan</h3>
                        <p class="mx-auto mt-1.5 max-w-[240px] text-sm text-zinc-500">Buka quiz, jawab soal, submit kapan saja.</p>
                    </div>
                    <div class="relative">
                        <div class="mx-auto flex size-11 items-center justify-center rounded-full bg-accent font-display text-lg font-bold text-white">3</div>
                        <h3 class="mt-4 font-display text-base font-bold text-zinc-900">Nilai Otomatis Keluar</h3>
                        <p class="mx-auto mt-1.5 max-w-[240px] text-sm text-zinc-500">Skor &amp; breakdown per soal langsung tampil.</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="px-6 py-16">
            <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-6 rounded-3xl bg-linear-to-br from-accent to-blue-900 px-8 py-12 sm:flex-row sm:px-14">
                <div class="text-center sm:text-left">
                    <h2 class="font-display text-2xl font-extrabold text-white">Siap Coba Sendiri?</h2>
                    <p class="mt-2 text-sm text-blue-100">Daftar gratis, bikin quiz pertamamu hari ini.</p>
                </div>
                <flux:button :href="route('register')" wire:navigate class="!bg-white !text-accent shadow-lg transition hover:!bg-blue-50 hover:-translate-y-0.5">
                    Mulai Sekarang
                    <flux:icon.arrow-right class="size-4" />
                </flux:button>
            </div>
        </section>

        <footer class="border-t border-zinc-200 px-6 py-8">
            <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-4 sm:flex-row">
                <div class="flex items-center gap-2">
                    <x-app-logo-icon class="size-7 text-accent" />
                    <span class="font-display text-sm font-bold text-zinc-900">{{ config('app.name') }}</span>
                </div>
                <span class="text-sm text-zinc-400">&copy; {{ date('Y') }} {{ config('app.name') }}. Semua hak dilindungi.</span>
            </div>
        </footer>

        @fluxScripts
    </body>
</html>
