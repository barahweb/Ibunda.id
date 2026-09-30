<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class QuizAttempt extends Model
{
    use HasFactory, HasUuids;

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'quiz_id',
        'user_id',
        'started_at',
        'submitted_at',
        'score',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(QuizAnswer::class);
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function deadline(): ?Carbon
    {
        if ($this->quiz->time_limit_minutes === null) {
            return null;
        }

        return $this->started_at->copy()->addMinutes($this->quiz->time_limit_minutes);
    }

    public function remainingSeconds(): ?int
    {
        $deadline = $this->deadline();

        return $deadline === null ? null : max(0, $deadline->getTimestamp() - now()->getTimestamp());
    }

    public function isExpired(int $graceSeconds = 0): bool
    {
        $deadline = $this->deadline();

        return !$this->isCompleted()
            && $deadline !== null
            && $deadline->copy()->subSeconds($graceSeconds)->isPast();
    }

    public function hasPassed(): ?bool
    {
        if (!$this->isCompleted() || $this->quiz->passing_score === null) {
            return null;
        }

        return $this->score >= $this->quiz->passing_score;
    }
}
