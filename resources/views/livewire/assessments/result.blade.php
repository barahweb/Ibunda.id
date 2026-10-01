<?php

use App\Helper\OejtsScorer;
use App\Models\AssessmentAttempt;
use App\Services\AssessmentInterpretationService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
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

    #[Computed]
    public function canRequestInterpretation(): bool
    {
        return blank($this->attempt->ai_interpretation)
            && Auth::user()->can('interpret', $this->attempt)
            && app(AssessmentInterpretationService::class)->isConfigured();
    }

    public function generateInterpretation(AssessmentInterpretationService $interpretationService): void
    {
        $this->authorize('interpret', $this->attempt);

        $key = 'assessment-interpretation:'.Auth::id();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            Flux::toast(text: 'Terlalu banyak permintaan, coba lagi nanti.', variant: 'danger');

            return;
        }

        RateLimiter::hit($key, 3600);

        try {
            $interpretationService->generate($this->attempt);
        } catch (\DomainException $e) {
            Flux::toast(text: $e->getMessage(), variant: 'danger');
        }

        $this->attempt->refresh();
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
     * Radar 4 sumbu: tiap sumbu = huruf yang menang di dimensinya, panjangnya = seberapa kuat
     * condongnya (0 di titik netral, penuh di skor ekstrem).
     *
     * @return array{polygon: string, rings: array<int, string>, axes: array<int, array{x: float, y: float, anchor: string, letter: string, label: string, strength: int}>}
     */
    #[Computed]
    public function radar(): array
    {
        $center = 150;
        $radius = 100;
        $angles = [-90, 0, 90, 180];
        $point = fn (int $index, float $fraction): array => [
            round($center + $radius * $fraction * cos(deg2rad($angles[$index])), 1),
            round($center + $radius * $fraction * sin(deg2rad($angles[$index])), 1),
        ];
        $names = ['E' => 'Extraversion', 'I' => 'Introversion', 'S' => 'Sensing', 'N' => 'Intuition', 'T' => 'Thinking', 'F' => 'Feeling', 'J' => 'Judging', 'P' => 'Perceiving'];

        $axes = [];
        $polygon = [];

        foreach ($this->dimensions as $index => $row) {
            $strength = (int) round(abs($row['percent'] - 50) * 2);
            [$x, $y] = $point($index, max($strength, 8) / 100);
            $polygon[] = "$x,$y";

            [$labelX, $labelY] = $point($index, 1.28);

            $axes[] = [
                'x' => $labelX,
                'y' => $labelY,
                'anchor' => match ($index) {
                    1 => 'start',
                    3 => 'end',
                    default => 'middle',
                },
                'letter' => $row['letter'],
                'label' => $names[$row['letter']],
                'strength' => $strength,
            ];
        }

        $rings = collect([0.25, 0.5, 0.75, 1])
            ->map(fn (float $fraction) => collect(range(0, 3))->map(fn (int $index) => implode(',', $point($index, $fraction)))->implode(' '))
            ->all();

        return ['polygon' => implode(' ', $polygon), 'rings' => $rings, 'axes' => $axes];
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

    <div class="rounded-2xl border border-zinc-200 p-5 dark:border-zinc-700">
        <flux:heading size="lg" class="font-display">Peta kepribadianmu</flux:heading>
        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Makin jauh dari tengah, makin kuat kecenderunganmu di sisi itu.</p>

        <svg viewBox="-20 0 340 300" class="mx-auto mt-4 w-full max-w-md" role="img" aria-label="Grafik radar kecenderungan kepribadian {{ $attempt->result_type }}">
            @foreach ($this->radar['rings'] as $ring)
                <polygon points="{{ $ring }}" class="fill-none stroke-zinc-200 dark:stroke-zinc-700" stroke-width="1" />
            @endforeach
            <line x1="150" y1="50" x2="150" y2="250" class="stroke-zinc-200 dark:stroke-zinc-700" />
            <line x1="50" y1="150" x2="250" y2="150" class="stroke-zinc-200 dark:stroke-zinc-700" />

            <polygon
                points="{{ $this->radar['polygon'] }}"
                class="animate-radar-grow fill-accent/20 stroke-accent motion-reduce:animate-none"
                style="transform-origin: 150px 150px; animation-delay: 700ms"
                stroke-width="2.5"
                stroke-linejoin="round"
            />

            @foreach ($this->radar['axes'] as $axis)
                <text x="{{ $axis['x'] }}" y="{{ $axis['y'] }}" text-anchor="{{ $axis['anchor'] }}" class="fill-zinc-700 text-[13px] font-semibold dark:fill-zinc-200">
                    {{ $axis['letter'] }} &middot; {{ $axis['label'] }}
                    <tspan x="{{ $axis['x'] }}" dy="15" class="fill-zinc-400 text-[11px] font-normal">{{ $axis['strength'] }}%</tspan>
                </text>
            @endforeach
        </svg>
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

    @if ($attempt->ai_interpretation || $this->canRequestInterpretation)
        <div class="rounded-2xl border border-zinc-200 p-5 dark:border-zinc-700">
            <div class="flex items-center gap-2">
                <flux:icon.sparkles class="size-5 text-accent" />
                <flux:heading size="lg" class="font-display">Interpretasi AI</flux:heading>
            </div>

            @if ($attempt->ai_interpretation)
                <p class="mt-3 whitespace-pre-line text-sm leading-relaxed text-zinc-700 dark:text-zinc-300">{{ $attempt->ai_interpretation }}</p>
            @else
                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Dapatkan penjelasan personal dari skor 4 dimensimu. Hanya tipe dan skor yang dikirim ke layanan AI, tanpa nama atau emailmu.</p>

                <flux:button variant="primary" icon="sparkles" wire:click="generateInterpretation" wire:loading.attr="disabled" wire:target="generateInterpretation" class="mt-4 transition hover:-translate-y-0.5">
                    <span wire:loading.remove wire:target="generateInterpretation">Buat Interpretasi AI</span>
                    <span wire:loading wire:target="generateInterpretation">Sedang menulis...</span>
                </flux:button>
            @endif

            <p class="mt-3 text-xs text-zinc-400">Dibuat oleh AI dari skor tesmu. Ini bahan refleksi diri, bukan diagnosis psikologis.</p>
        </div>
    @endif

    <x-oejts-attribution />
</div>
