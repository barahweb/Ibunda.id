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

    /**
     * Data buat digambar ke kartu PNG di sisi browser.
     *
     * @return array<string, mixed>
     */
    #[Computed]
    public function cardData(): array
    {
        return [
            'title' => $this->attempt->assessment->title,
            'type' => $this->attempt->result_type,
            'name' => $this->typeDescription['name'] ?? '',
            'description' => $this->typeDescription['description'] ?? '',
            'participant' => $this->attempt->user->name,
            'date' => $this->attempt->submitted_at->format('d M Y'),
            'dimensions' => $this->dimensions,
            'filename' => 'hasil-tes-kepribadian-'.strtolower($this->attempt->result_type).'.png',
        ];
    }

    #[Computed]
    public function typeDescription(): ?array
    {
        return config('oejts.types.'.$this->attempt->result_type);
    }
}; ?>

<div
    class="flex flex-col gap-6"
    x-data="{
        card: @js($this->cardData),
        celebrate: @js(request()->boolean('baru')),
        reduced: window.matchMedia('(prefers-reduced-motion: reduce)').matches,
        init() {
            if (this.celebrate && !this.reduced) {
                this.confetti();
            }
        },
        confetti() {
            const canvas = this.$refs.confetti;
            const ctx = canvas.getContext('2d');
            const colors = ['#2563eb', '#f59e0b', '#10b981', '#ef4444', '#8b5cf6', '#ec4899'];
            const resize = () => { canvas.width = innerWidth; canvas.height = innerHeight; };
            resize();
            const pieces = Array.from({ length: 140 }, () => ({
                x: Math.random() * canvas.width,
                y: -20 - Math.random() * canvas.height * 0.6,
                w: 6 + Math.random() * 6,
                h: 10 + Math.random() * 8,
                vx: -1.5 + Math.random() * 3,
                vy: 2 + Math.random() * 3.5,
                rot: Math.random() * Math.PI,
                vr: -0.15 + Math.random() * 0.3,
                color: colors[Math.floor(Math.random() * colors.length)],
            }));
            const end = performance.now() + 4500;
            const frame = (now) => {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                pieces.forEach((p) => {
                    p.x += p.vx; p.y += p.vy; p.rot += p.vr;
                    ctx.save();
                    ctx.translate(p.x, p.y);
                    ctx.rotate(p.rot);
                    ctx.fillStyle = p.color;
                    ctx.globalAlpha = Math.max(0, Math.min(1, (end - now) / 1200));
                    ctx.fillRect(-p.w / 2, -p.h / 2, p.w, p.h);
                    ctx.restore();
                });
                if (now < end) {
                    requestAnimationFrame(frame);
                } else {
                    ctx.clearRect(0, 0, canvas.width, canvas.height);
                }
            };
            requestAnimationFrame(frame);
        },
        wrap(ctx, text, maxWidth) {
            const lines = [];
            let line = '';
            text.split(' ').forEach((word) => {
                const test = line ? line + ' ' + word : word;
                if (ctx.measureText(test).width > maxWidth && line) {
                    lines.push(line);
                    line = word;
                } else {
                    line = test;
                }
            });
            if (line) lines.push(line);
            return lines;
        },
        download() {
            const c = this.card;
            const W = 1080, H = 1350, pad = 80;
            const canvas = document.createElement('canvas');
            canvas.width = W; canvas.height = H;
            const ctx = canvas.getContext('2d');
            const font = 'system-ui, -apple-system, Segoe UI, Roboto, sans-serif';

            const bg = ctx.createLinearGradient(0, 0, W, H);
            bg.addColorStop(0, '#eff6ff'); bg.addColorStop(1, '#ffffff');
            ctx.fillStyle = bg; ctx.fillRect(0, 0, W, H);
            ctx.fillStyle = '#2563eb'; ctx.fillRect(0, 0, W, 16);

            ctx.textAlign = 'center';
            ctx.fillStyle = '#52525b'; ctx.font = '600 34px ' + font;
            ctx.fillText(c.title, W / 2, 120);

            ctx.fillStyle = '#2563eb'; ctx.font = '800 200px ' + font;
            ctx.fillText(c.type.split('').join(' '), W / 2, 340);

            ctx.fillStyle = '#18181b'; ctx.font = '700 52px ' + font;
            ctx.fillText(c.name, W / 2, 430);

            ctx.fillStyle = '#52525b'; ctx.font = '30px ' + font;
            let y = 495;
            this.wrap(ctx, c.description, W - pad * 2).forEach((l) => { ctx.fillText(l, W / 2, y); y += 42; });

            y = Math.max(y + 50, 640);
            c.dimensions.forEach((d) => {
                const l = d.dimension[0], r = d.dimension[1];
                ctx.font = '600 28px ' + font;
                ctx.textAlign = 'left';
                ctx.fillStyle = d.letter === l ? '#2563eb' : '#a1a1aa';
                ctx.fillText(d.left + ' (' + l + ')', pad, y);
                ctx.textAlign = 'right';
                ctx.fillStyle = d.letter === r ? '#2563eb' : '#a1a1aa';
                ctx.fillText(d.right + ' (' + r + ')', W - pad, y);

                const bw = W - pad * 2;
                ctx.fillStyle = '#e4e4e7';
                ctx.beginPath(); ctx.roundRect(pad, y + 20, bw, 18, 9); ctx.fill();
                ctx.fillStyle = '#d4d4d8'; ctx.fillRect(pad + bw / 2 - 1, y + 20, 2, 18);
                ctx.fillStyle = '#2563eb';
                ctx.beginPath(); ctx.arc(pad + bw * d.percent / 100, y + 29, 17, 0, Math.PI * 2); ctx.fill();
                ctx.strokeStyle = '#ffffff'; ctx.lineWidth = 4; ctx.stroke();
                y += 130;
            });

            ctx.textAlign = 'center';
            ctx.fillStyle = '#71717a'; ctx.font = '26px ' + font;
            ctx.fillText(c.participant + '  |  ' + c.date, W / 2, H - 120);
            ctx.fillStyle = '#a1a1aa'; ctx.font = '22px ' + font;
            ctx.fillText('Open Extended Jungian Type Scales (OEJTS) 1.2, Open Psychometrics, CC BY-NC-SA 4.0', W / 2, H - 70);

            canvas.toBlob((blob) => {
                const a = document.createElement('a');
                a.href = URL.createObjectURL(blob);
                a.download = c.filename;
                a.click();
                setTimeout(() => URL.revokeObjectURL(a.href), 1000);
            }, 'image/png');
        },
    }"
>
    <canvas x-ref="confetti" class="pointer-events-none fixed inset-0 z-50" aria-hidden="true"></canvas>

    <div>
        <flux:link href="#" onclick="window.history.back(); return false;" class="text-sm">&larr; Kembali</flux:link>
    </div>

    <div class="rounded-2xl border border-zinc-200 p-10 text-center dark:border-zinc-700">
        <flux:heading size="xl" class="font-display">{{ $attempt->assessment->title }}</flux:heading>
        <p class="mt-2 flex justify-center gap-2 font-display text-6xl font-extrabold text-accent sm:gap-4" aria-label="{{ $attempt->result_type }}">
            @foreach (str_split($attempt->result_type) as $index => $letter)
                <span
                    class="inline-block animate-letter-pop motion-reduce:animate-none"
                    style="animation-delay: {{ $index * 180 }}ms"
                    aria-hidden="true"
                >{{ $letter }}</span>
            @endforeach
        </p>
        <div class="animate-fade-up motion-reduce:animate-none" style="animation-delay: 850ms">
            @if ($this->typeDescription)
                <p class="mt-2 text-lg font-semibold text-zinc-900 dark:text-white">{{ $this->typeDescription['name'] }}</p>
                <p class="mx-auto mt-2 max-w-xl text-sm text-zinc-500 dark:text-zinc-400">{{ $this->typeDescription['description'] }}</p>
            @endif
            <p class="mt-4 text-xs text-zinc-400">Dikerjakan oleh {{ $attempt->user->name }}, submit {{ $attempt->submitted_at->format('d M Y H:i') }}</p>
            <flux:button variant="primary" icon="arrow-down-tray" x-on:click="download()" class="mt-5 transition hover:-translate-y-0.5">Unduh Kartu Hasil (PNG)</flux:button>
        </div>
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
                    <div
                        class="absolute top-1/2 size-4 -translate-x-1/2 -translate-y-1/2 animate-marker-slide rounded-full bg-accent ring-2 ring-white motion-reduce:animate-none"
                        style="left: {{ $row['percent'] }}%; animation-delay: {{ 900 + $loop->index * 150 }}ms"
                    ></div>
                </div>
                <p class="mt-1 text-center text-xs text-zinc-400">Skor {{ $row['sum'] }} dari 40 (batas tengah 24)</p>
            </div>
        @endforeach
    </div>

    <x-oejts-attribution />
</div>
